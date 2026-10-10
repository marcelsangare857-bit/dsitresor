
<?php
session_start();

require_once __DIR__ . '/../config/connexion.php';

// 1. Vérifier la connexion
$idConnecte = $_SESSION['id_utilisateur']
    ?? $_SESSION['utilisateur_id']
    ?? $_SESSION['id']
    ?? $_SESSION['utilisateur']['id']
    ?? null;

if (!$idConnecte) {
    header('Location: ../login.php');
    exit;
}

// 2. Réserver cette page à l'administrateur
$roleConnecte = $_SESSION['role']
    ?? $_SESSION['utilisateur']['role']
    ?? '';

if ($roleConnecte !== 'administrateur') {
    http_response_code(403);
    exit("Accès refusé : seuls les administrateurs peuvent modifier les utilisateurs.");
}

// 3. Créer le jeton de sécurité
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function e($valeur)
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES, 'UTF-8');
}

// 4. Vérifier l'identifiant transmis dans l'URL
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    exit("Identifiant utilisateur invalide.");
}

// 5. Charger l'utilisateur
$stmt = $pdo->prepare(
    "SELECT id, nom, prenom, email, role, actif
     FROM utilisateurs
     WHERE id = ?"
);
$stmt->execute([$id]);
$utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$utilisateur) {
    http_response_code(404);
    exit("Utilisateur introuvable.");
}

// 6. Valeurs initiales du formulaire
$nom = $utilisateur['nom'];
$prenom = $utilisateur['prenom'];
$email = $utilisateur['email'];
$role = $utilisateur['role'];
$actif = (int) $utilisateur['actif'];
$erreur = '';

$rolesAutorises = [
    'administrateur',
    'responsable_dsi',
    'technicien',
    'consultation'
];

// 7. Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Vérifier le jeton de sécurité
    $csrfRecu = $_POST['csrf'] ?? '';

    if (!is_string($csrfRecu) ||
        !hash_equals($_SESSION['csrf'], $csrfRecu)) {
        $erreur = "Formulaire expiré ou invalide. Rechargez la page.";
    } else {
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';
        $actif = isset($_POST['actif']) ? (int) $_POST['actif'] : -1;
        $nouveauMotDePasse = $_POST['mot_de_passe'] ?? '';

        // Vérifier les champs obligatoires
        if ($nom === '' || $prenom === '' || $email === '') {
            $erreur = "Veuillez remplir le nom, le prénom et l'e-mail.";
        } elseif (
            mb_strlen($nom) > 100 ||
            mb_strlen($prenom) > 100
        ) {
            $erreur = "Le nom et le prénom ne doivent pas dépasser 100 caractères.";
        } elseif (
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($email) > 150
        ) {
            $erreur = "Adresse e-mail invalide.";
        } elseif (!in_array($role, $rolesAutorises, true)) {
            $erreur = "Le rôle sélectionné est invalide.";
        } elseif (!in_array($actif, [0, 1], true)) {
            $erreur = "Le statut sélectionné est invalide.";
        } elseif (
            $nouveauMotDePasse !== '' &&
            strlen($nouveauMotDePasse) < 12
        ) {
            $erreur = "Le nouveau mot de passe doit contenir au moins 12 caractères.";
        } elseif (
            $nouveauMotDePasse !== '' &&
            strlen($nouveauMotDePasse) > 72
        ) {
            $erreur = "Le mot de passe ne doit pas dépasser 72 octets.";
        } else {
            // Empêcher les e-mails en double
            $stmt = $pdo->prepare(
                "SELECT id FROM utilisateurs
                 WHERE email = ? AND id <> ?"
            );
            $stmt->execute([$email, $id]);

            if ($stmt->fetch()) {
                $erreur = "Cette adresse e-mail est déjà utilisée.";
            } else {
                // Empêcher de désactiver son propre compte
                if ((int) $id === (int) $idConnecte && $actif === 0) {
                    $erreur = "Vous ne pouvez pas désactiver votre propre compte.";
                }

                // Empêcher de retirer son propre rôle administrateur
                if (
                    (int) $id === (int) $idConnecte &&
                    $role !== 'administrateur'
                ) {
                    $erreur = "Vous ne pouvez pas retirer votre propre rôle administrateur.";
                }

                // Empêcher de désactiver ou rétrograder le dernier administrateur actif
                if (
                    $erreur === '' &&
                    $utilisateur['role'] === 'administrateur' &&
                    (int) $utilisateur['actif'] === 1 &&
                    ($role !== 'administrateur' || $actif !== 1)
                ) {
                    $stmt = $pdo->prepare(
                        "SELECT COUNT(*) FROM utilisateurs
                         WHERE role = 'administrateur'
                         AND actif = 1
                         AND id <> ?"
                    );
                    $stmt->execute([$id]);

                    if ((int) $stmt->fetchColumn() === 0) {
                        $erreur = "Impossible de désactiver ou rétrograder le dernier administrateur actif.";
                    }
                }

                if ($erreur === '') {
                    try {
                        if ($nouveauMotDePasse !== '') {
                            $hash = password_hash(
                                $nouveauMotDePasse,
                                PASSWORD_DEFAULT
                            );

                            $sql = "UPDATE utilisateurs
                                    SET nom = ?, prenom = ?, email = ?,
                                        role = ?, actif = ?, mot_de_passe = ?
                                    WHERE id = ?";

                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([
                                $nom, $prenom, $email,
                                $role, $actif, $hash, $id
                            ]);
                        } else {
                            $sql = "UPDATE utilisateurs
                                    SET nom = ?, prenom = ?, email = ?,
                                        role = ?, actif = ?
                                    WHERE id = ?";

                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([
                                $nom, $prenom, $email,
                                $role, $actif, $id
                            ]);
                        }

                        // Actualiser la session si l'administrateur
                        // modifie son propre nom ou son e-mail
                        if ((int) $id === (int) $idConnecte) {
                            $_SESSION['nom'] = $nom;
                            $_SESSION['prenom'] = $prenom;
                            $_SESSION['email'] = $email;
                            $_SESSION['role'] = $role;

                            if (isset($_SESSION['utilisateur']) &&
                                is_array($_SESSION['utilisateur'])) {
                                $_SESSION['utilisateur']['nom'] = $nom;
                                $_SESSION['utilisateur']['prenom'] = $prenom;
                                $_SESSION['utilisateur']['email'] = $email;
                                $_SESSION['utilisateur']['role'] = $role;
                            }
                        }

                        header('Location: liste.php?message=modifie');
                        exit;

                    } catch (PDOException $ex) {
                        $erreur = "Erreur lors de la modification. Vérifiez les données puis réessayez.";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un utilisateur - DSI Trésor Mali</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        header {
            background: #123b66;
            color: white;
            padding: 22px 30px;
        }

        header h1 {
            margin: 0 0 8px;
        }

        header p {
            margin: 0;
            color: #dbeafe;
        }

        main {
            max-width: 750px;
            margin: 30px auto;
            padding: 28px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .08);
        }

        label {
            display: block;
            margin: 17px 0 7px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 11px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            font-size: 15px;
        }

        input:focus, select:focus {
            outline: 2px solid #93c5fd;
            border-color: #2563eb;
        }

        .aide {
            color: #64748b;
            font-size: 13px;
            margin-top: 6px;
        }

        .erreur {
            padding: 12px;
            margin-bottom: 18px;
            border-radius: 5px;
            background: #fee2e2;
            color: #991b1b;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 25px;
        }

        .btn {
            display: inline-block;
            padding: 11px 16px;
            border: none;
            border-radius: 5px;
            color: white;
            background: #2563eb;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-retour {
            background: #475569;
        }

        footer {
            text-align: center;
            padding: 20px;
            color: #64748b;
            font-size: 13px;
        }

        @media (max-width: 600px) {
            main {
                margin: 15px;
                padding: 18px;
            }

            header {
                padding: 20px;
            }
        }
    </style>
</head>

<body>
<header>
    <h1>DSI Trésor Mali</h1>
    <p>Gestion des utilisateurs et des rôles</p>
</header>

<main>
    <h2>Modifier un utilisateur</h2>
    <p>Utilisateur n° <?= (int) $utilisateur['id'] ?></p>

    <?php if ($erreur !== ''): ?>
        <div class="erreur"><?= e($erreur) ?></div>
    <?php endif; ?>

    <form method="post" action="modifier.php?id=<?= (int) $id ?>">
        <input
            type="hidden"
            name="csrf"
            value="<?= e($_SESSION['csrf']) ?>"
        >

        <label for="nom">Nom</label>
        <input
            type="text"
            id="nom"
            name="nom"
            maxlength="100"
            value="<?= e($nom) ?>"
            required
        >

        <label for="prenom">Prénom</label>
        <input
            type="text"
            id="prenom"
            name="prenom"
            maxlength="100"
            value="<?= e($prenom) ?>"
            required
        >

        <label for="email">Adresse e-mail</label>
        <input
            type="email"
            id="email"
            name="email"
            maxlength="150"
            value="<?= e($email) ?>"
            required
        >

        <label for="role">Rôle de l'utilisateur</label>
        <select id="role" name="role" required>
            <option value="administrateur"
                <?= $role === 'administrateur' ? 'selected' : '' ?>>
                Administrateur
            </option>
            <option value="responsable_dsi"
                <?= $role === 'responsable_dsi' ? 'selected' : '' ?>>
                Responsable DSI
            </option>
            <option value="technicien"
                <?= $role === 'technicien' ? 'selected' : '' ?>>
                Technicien
            </option>
            <option value="consultation"
                <?= $role === 'consultation' ? 'selected' : '' ?>>
                Consultation seule
            </option>
        </select>

        <label for="actif">Statut du compte</label>
        <select id="actif" name="actif" required>
            <option value="1" <?= $actif === 1 ? 'selected' : '' ?>>
                Actif
            </option>
            <option value="0" <?= $actif === 0 ? 'selected' : '' ?>>
                Désactivé
            </option>
        </select>

        <label for="mot_de_passe">Nouveau mot de passe (facultatif)</label>
        <input
            type="password"
            id="mot_de_passe"
            name="mot_de_passe"
            minlength="12"
            maxlength="72"
            autocomplete="new-password"
            placeholder="Laisser vide pour conserver l'ancien"
        >
        <p class="aide">
            Si tu ne souhaites pas changer le mot de passe,
            laisse ce champ vide. Sinon, utilise au moins 12 caractères.
        </p>

        <div class="actions">
            <button type="submit" class="btn">
                Enregistrer les modifications
            </button>

            <a href="liste.php" class="btn btn-retour">
                Annuler et retourner à la liste
            </a>
        </div>
    </form>
</main>

<footer>
    DSI Trésor Mali &copy; <?= date('Y') ?>
</footer>
</body>
</html>


<?php
session_start();

require_once __DIR__ . '/../config/connexion.php';

// 1. Vérifier que l'utilisateur est connecté
$idConnecte = $_SESSION['id_utilisateur']
    ?? $_SESSION['utilisateur_id']
    ?? $_SESSION['id']
    ?? $_SESSION['utilisateur']['id']
    ?? null;

if (!$idConnecte) {
    header('Location: ../login.php');
    exit;
}

// 2. Seul l'administrateur peut ajouter un utilisateur
$roleConnecte = $_SESSION['role']
    ?? $_SESSION['utilisateur']['role']
    ?? '';

if ($roleConnecte !== 'administrateur') {
    http_response_code(403);
    exit("Accès refusé : seuls les administrateurs peuvent ajouter des utilisateurs.");
}

// 3. Créer un jeton de sécurité CSRF
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// 4. Fonction de protection de l'affichage HTML
function e($valeur)
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES, 'UTF-8');
}

// 5. Initialiser les variables
$nom = '';
$prenom = '';
$email = '';
$role = 'consultation';
$actif = 1;
$erreur = '';

$rolesAutorises = [
    'administrateur',
    'responsable_dsi',
    'technicien',
    'consultation'
];

// 6. Traiter le formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Vérifier le jeton CSRF
    $csrfRecu = $_POST['csrf'] ?? '';

    if (
        !is_string($csrfRecu) ||
        !hash_equals($_SESSION['csrf'], $csrfRecu)
    ) {
        $erreur = "Formulaire invalide. Rechargez la page puis réessayez.";
    } else {

        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';
        $actif = isset($_POST['actif']) ? (int) $_POST['actif'] : -1;
        $motDePasse = $_POST['mot_de_passe'] ?? '';
        $confirmation = $_POST['confirmation'] ?? '';

        // Vérifier les champs obligatoires
        if ($nom === '' || $prenom === '' || $email === '') {
            $erreur = "Veuillez remplir le nom, le prénom et l'adresse e-mail.";

        } elseif (
            mb_strlen($nom) > 100 ||
            mb_strlen($prenom) > 100
        ) {
            $erreur = "Le nom et le prénom ne doivent pas dépasser 100 caractères.";

        } elseif (
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($email) > 150
        ) {
            $erreur = "L'adresse e-mail est invalide.";

        } elseif (!in_array($role, $rolesAutorises, true)) {
            $erreur = "Le rôle sélectionné est invalide.";

        } elseif (!in_array($actif, [0, 1], true)) {
            $erreur = "Le statut sélectionné est invalide.";

        } elseif (strlen($motDePasse) < 12) {
            $erreur = "Le mot de passe doit contenir au moins 12 caractères.";

        } elseif (strlen($motDePasse) > 72) {
            $erreur = "Le mot de passe ne doit pas dépasser 72 octets.";

        } elseif ($motDePasse !== $confirmation) {
            $erreur = "Les deux mots de passe ne correspondent pas.";

        } else {
            try {
                // Vérifier que l'adresse e-mail n'existe pas déjà
                $stmt = $pdo->prepare(
                    "SELECT id FROM utilisateurs WHERE email = ?"
                );
                $stmt->execute([$email]);

                if ($stmt->fetch()) {
                    $erreur = "Cette adresse e-mail est déjà utilisée.";
                } else {

                    // Sécuriser le mot de passe
                    $hash = password_hash(
                        $motDePasse,
                        PASSWORD_DEFAULT
                    );

                    // Enregistrer le nouvel utilisateur
                    $sql = "INSERT INTO utilisateurs
                            (nom, prenom, email, mot_de_passe, role, actif)
                            VALUES (?, ?, ?, ?, ?, ?)";

                    $stmt = $pdo->prepare($sql);

                    $stmt->execute([
                        $nom,
                        $prenom,
                        $email,
                        $hash,
                        $role,
                        $actif
                    ]);

                    // Rediriger vers la liste
                    header('Location: liste.php?message=ajoute');
                    exit;
                }

            } catch (PDOException $e) {
                $erreur = "Une erreur est survenue lors de l'enregistrement.";
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

    <title>Ajouter un utilisateur - DSI Trésor Mali</title>

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
    <h2>Ajouter un utilisateur</h2>

    <p>Renseignez les informations du nouvel utilisateur.</p>

    <?php if ($erreur !== ''): ?>
        <div class="erreur"><?= e($erreur) ?></div>
    <?php endif; ?>

    <form method="post" action="ajouter.php">

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

        <label for="role">Rôle</label>
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

        <label for="mot_de_passe">Mot de passe</label>
        <input
            type="password"
            id="mot_de_passe"
            name="mot_de_passe"
            minlength="12"
            maxlength="72"
            autocomplete="new-password"
            required
        >
        <p class="aide">
            Utilisez au moins 12 caractères.
        </p>

        <label for="confirmation">Confirmer le mot de passe</label>
        <input
            type="password"
            id="confirmation"
            name="confirmation"
            minlength="12"
            maxlength="72"
            autocomplete="new-password"
            required
        >

        <div class="actions">
            <button type="submit" class="btn">
                Enregistrer l'utilisateur
            </button>

            <a href="liste.php" class="btn btn-retour">
                Retour à la liste
            </a>
        </div>

    </form>
</main>

<footer>
    DSI Trésor Mali &copy; <?= date('Y') ?>
</footer>

</body>
</html>



<?php
session_start();

// Si l'utilisateur est déjà connecté, afficher le tableau de bord
if (isset($_SESSION['id_utilisateur'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/config/connexion.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nomUtilisateur = trim($_POST['nom_utilisateur'] ?? '');
    $motDePasse = $_POST['mot_de_passe'] ?? '';

    if ($nomUtilisateur === '' || $motDePasse === '') {
        $message = 'Veuillez remplir tous les champs.';
    } else {
        try {
            $sql = "SELECT id_utilisateur, nom_utilisateur,
                           mot_de_passe, role, actif
                    FROM utilisateurs
                    WHERE nom_utilisateur = :nom
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute(['nom' => $nomUtilisateur]);

            $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                $utilisateur &&
                (int)$utilisateur['actif'] === 1 &&
                password_verify($motDePasse, $utilisateur['mot_de_passe'])
            ) {
                session_regenerate_id(true);

                $_SESSION['id_utilisateur'] =
                    $utilisateur['id_utilisateur'];

                $_SESSION['nom_utilisateur'] =
                    $utilisateur['nom_utilisateur'];

                $_SESSION['role'] = $utilisateur['role'];

                header('Location: index.php');
                exit;
            } else {
                $message = 'Nom d’utilisateur ou mot de passe incorrect, ou compte inactif.';
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $message = 'Erreur technique. Vérifiez la configuration de la base de données.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - DSI Trésor Mali</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #123c69, #2563a6);
            font-family: Arial, sans-serif;
        }

        .connexion {
            background: #fff;
            width: 100%;
            max-width: 420px;
            margin: 20px;
            padding: 32px;
            border-radius: 12px;
            box-shadow: 0 8px 30px #0003;
        }

        h1 {
            text-align: center;
            color: #123c69;
            margin-top: 0;
        }

        .sous-titre {
            text-align: center;
            color: #64748b;
            margin-bottom: 28px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;
            margin-bottom: 18px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 15px;
        }

        button {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 6px;
            background: #123c69;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #1d4f91;
        }

        .erreur {
            padding: 12px;
            margin-bottom: 18px;
            color: #991b1b;
            background: #fee2e2;
            border-radius: 6px;
        }

        .pied {
            margin-top: 24px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
        }
    </style>
</head>

<body>
    <main class="connexion">
        <h1>DSI Trésor Mali</h1>

        <p class="sous-titre">
            Connexion à votre espace de travail
        </p>

        <?php if ($message !== ''): ?>
            <div class="erreur">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <label for="nom_utilisateur">
                Nom d'utilisateur
            </label>

            <input
                type="text"
                id="nom_utilisateur"
                name="nom_utilisateur"
                autocomplete="username"
                required
            >

            <label for="mot_de_passe">
                Mot de passe
            </label>

            <input
                type="password"
                id="mot_de_passe"
                name="mot_de_passe"
                autocomplete="current-password"
                required
            >

            <button type="submit">Se connecter</button>
        </form>

        <p class="pied">
            Accès réservé aux utilisateurs autorisés
        </p>
    </main>
</body>
</html>

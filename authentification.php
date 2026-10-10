
<?php
session_start();

require_once __DIR__ . '/config/connexion.php';

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';

    if ($email === '' || $mot_de_passe === '') {
        $erreur = 'Veuillez remplir tous les champs.';
    } else {
        try {
            $sql = "SELECT id, nom, prenom, email,
                           mot_de_passe, role
                    FROM utilisateurs
                    WHERE email = ? AND actif = 1
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email]);

            $utilisateur = $stmt->fetch();

            if (
                $utilisateur &&
                password_verify(
                    $mot_de_passe,
                    $utilisateur['mot_de_passe']
                )
            ) {
                session_regenerate_id(true);

                $_SESSION['id_utilisateur'] = $utilisateur['id'];
                $_SESSION['nom_utilisateur'] = $utilisateur['nom'];
                $_SESSION['prenom_utilisateur'] = $utilisateur['prenom'];
                $_SESSION['role'] = $utilisateur['role'];

                header('Location: index.php');
                exit;
            } else {
                $erreur = 'E-mail ou mot de passe incorrect.';
            }
        } catch (PDOException $e) {
            $erreur = 'Erreur lors de la connexion à la base de données.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Authentification - DSI Trésor Mali</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #edf2f7;
            min-height: 100vh;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login {
            width: 350px;
            max-width: calc(100% - 60px);
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px #0002;
        }

        h1, h2 {
            text-align: center;
            color: #12345a;
        }

        label {
            display: block;
            margin-top: 15px;
        }

        input {
            width: 100%;
            box-sizing: border-box;
            padding: 11px;
            margin-top: 6px;
        }

        button {
            width: 100%;
            padding: 12px;
            margin-top: 20px;
            color: white;
            background: #12345a;
            border: none;
            cursor: pointer;
        }

        .erreur {
            color: #991b1b;
            background: #fee2e2;
            padding: 10px;
        }
    </style>
</head>
<body>

<div class="login">
    <h1>DSI Trésor Mali</h1>
    <h2>Authentification</h2>

    <?php if ($erreur !== ''): ?>
        <p class="erreur">
            <?= htmlspecialchars($erreur, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form action="authentification.php" method="post">
        <label for="email">Adresse e-mail</label>
        <input type="email" id="email" name="email"
               autocomplete="username" required>

        <label for="mot_de_passe">Mot de passe</label>
        <input type="password" id="mot_de_passe"
               name="mot_de_passe"
               autocomplete="current-password" required>

        <button type="submit">Se connecter</button>
    </form>
</div>

</body>
</html>


<?php
session_start();

require_once __DIR__ . '/config/connexion.php';

$message = '';
$erreur = '';

// Enregistrer une intervention
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $numero = trim($_POST['numero_intervention'] ?? '');
        $idIncident = !empty($_POST['id_incident'])
            ? (int) $_POST['id_incident']
            : null;
        $idEquipement = !empty($_POST['id_equipement'])
            ? (int) $_POST['id_equipement']
            : null;
        $technicien = trim($_POST['technicien'] ?? '');
        $dateIntervention = $_POST['date_intervention'] ?? date('Y-m-d');
        $diagnostic = trim($_POST['diagnostic'] ?? '');
        $actions = trim($_POST['actions_realisees'] ?? '');
        $duree = !empty($_POST['duree_minutes'])
            ? (int) $_POST['duree_minutes']
            : null;
        $resultat = trim($_POST['resultat'] ?? '');
        $observation = trim($_POST['observation'] ?? '');

        if ($numero === '') {
            throw new Exception("Le numéro d'intervention est obligatoire.");
        }

        // Vérifier que le numéro n'existe pas déjà
        $verification = $pdo->prepare(
            "SELECT COUNT(*) FROM interventions
             WHERE numero_intervention = ?"
        );
        $verification->execute([$numero]);

        if ($verification->fetchColumn() > 0) {
            throw new Exception("Ce numéro d'intervention existe déjà.");
        }

        $sql = "INSERT INTO interventions (
                    numero_intervention,
                    id_incident,
                    id_equipement,
                    technicien,
                    date_intervention,
                    diagnostic,
                    actions_realisees,
                    duree_minutes,
                    resultat,
                    observation
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $numero,
            $idIncident,
            $idEquipement,
            $technicien,
            $dateIntervention,
            $diagnostic,
            $actions,
            $duree,
            $resultat,
            $observation
        ]);

        $message = "Intervention enregistrée avec succès.";

    } catch (Exception $e) {
        $erreur = $e->getMessage();
    } catch (PDOException $e) {
        $erreur = "Erreur de base de données : " . $e->getMessage();
    }
}

// Charger les équipements
$equipements = $pdo->query(
    "SELECT id, designation, code_inventaire
     FROM equipements
     ORDER BY designation"
)->fetchAll();

// Charger les incidents
$incidents = $pdo->query(
    "SELECT numero_incident, titre
     FROM incidents
     ORDER BY numero_incident DESC"
)->fetchAll();

// Charger les interventions
$interventions = $pdo->query(
    "SELECT *
     FROM interventions
     ORDER BY date_intervention DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des interventions - DSI Trésor</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        header {
            background: #12345a;
            color: white;
            padding: 20px;
        }

        header h1 {
            margin: 0 0 8px;
        }

        nav {
            margin-top: 15px;
        }

        nav a {
            color: white;
            margin-right: 18px;
            text-decoration: none;
        }

        main {
            max-width: 1200px;
            margin: 25px auto;
            padding: 0 15px;
        }

        .carte {
            background: white;
            padding: 22px;
            margin-bottom: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px #00000012;
        }

        .grille {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 15px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        textarea {
            min-height: 85px;
            resize: vertical;
        }

        button {
            background: #087443;
            color: white;
            padding: 12px 20px;
            border: 0;
            border-radius: 5px;
            cursor: pointer;
        }

        button:hover {
            background: #065f38;
        }

        .succes {
            background: #dcfce7;
            padding: 12px;
            margin-bottom: 15px;
            color: #166534;
        }

        .erreur {
            background: #fee2e2;
            padding: 12px;
            margin-bottom: 15px;
            color: #991b1b;
            overflow-wrap: anywhere;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }

        th {
            background: #e2e8f0;
        }

        footer {
            text-align: center;
            padding: 20px;
            color: #64748b;
        }
    </style>
</head>

<body>

<header>
    <h1>DSI Trésor Mali</h1>
    <p>Gestion des interventions informatiques</p>

    <nav>
        <a href="index.php">Accueil</a>
        <a href="equipements.php">Équipements</a>
        <a href="incidents.php">Incidents</a>
        <a href="interventions.php">Interventions</a>
    </nav>
</header>

<main>

    <section class="carte">
        <h2>Nouvelle intervention</h2>

        <?php if ($message !== ''): ?>
            <div class="succes">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($erreur !== ''): ?>
            <div class="erreur">
                <?= htmlspecialchars($erreur, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="post" action="interventions.php">

            <div class="grille">
                <div>
                    <label for="numero_intervention">Numéro d'intervention *</label>
                    <input
                        type="text"
                        id="numero_intervention"
                        name="numero_intervention"
                        placeholder="Ex. INT-2026-001"
                        required
                    >
                </div>

                <div>
                    <label for="id_incident">Incident associé</label>
                    <select id="id_incident" name="id_incident">
                        <option value="">-- Aucun incident --</option>
                        <?php foreach ($incidents as $incident): ?>
                            <option value="<?= htmlspecialchars($incident['numero_incident'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($incident['numero_incident'] . ' - ' . $incident['titre'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="id_equipement">Équipement</label>
                    <select id="id_equipement" name="id_equipement">
                        <option value="">-- Choisir un équipement --</option>
                        <?php foreach ($equipements as $equipement): ?>
                            <option value="<?= (int) $equipement['id'] ?>">
                                <?= htmlspecialchars($equipement['code_inventaire'] . ' - ' . $equipement['designation'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="technicien">Technicien</label>
                    <input
                        type="text"
                        id="technicien"
                        name="technicien"
                        placeholder="Nom du technicien"
                    >
                </div>

                <div>
                    <label for="date_intervention">Date d'intervention</label>
                    <input
                        type="date"
                        id="date_intervention"
                        name="date_intervention"
                        value="<?= date('Y-m-d') ?>"
                        required
                    >
                </div>

                <div>
                    <label for="duree_minutes">Durée (minutes)</label>
                    <input
                        type="number"
                        id="duree_minutes"
                        name="duree_minutes"
                        min="0"
                        placeholder="Ex. 60"
                    >
                </div>

                <div>
                    <label for="resultat">Résultat</label>
                    <select id="resultat" name="resultat">
                        <option value="">-- Choisir --</option>
                        <option value="repare">Réparé</option>
                        <option value="partiel">Partiellement réparé</option>
                        <option value="non_repare">Non réparé</option>
                    </select>
                </div>
            </div>

            <label for="diagnostic">Diagnostic</label>
            <textarea
                id="diagnostic"
                name="diagnostic"
                placeholder="Décrire la panne constatée..."
            ></textarea>

            <label for="actions_realisees">Actions réalisées</label>
            <textarea
                id="actions_realisees"
                name="actions_realisees"
                placeholder="Décrire les opérations effectuées..."
            ></textarea>

            <label for="observation">Observations</label>
            <textarea
                id="observation"
                name="observation"
                placeholder="Observations complémentaires..."
            ></textarea>

            <button type="submit">Enregistrer l'intervention</button>
        </form>
    </section>

    <section class="carte">
        <h2>Liste des interventions</h2>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Numéro</th>
                        <th>Incident</th>
                        <th>Équipement ID</th>
                        <th>Technicien</th>
                        <th>Date</th>
                        <th>Durée (min)</th>
                        <th>Résultat</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (count($interventions) > 0): ?>
                    <?php foreach ($interventions as $intervention): ?>
                        <tr>
                            <td><?= htmlspecialchars($intervention['numero_intervention'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($intervention['id_incident'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($intervention['id_equipement'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($intervention['technicien'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($intervention['date_intervention'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($intervention['duree_minutes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($intervention['resultat'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">Aucune intervention enregistrée.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

<footer>
    DSI Trésor Mali — Gestion du parc informatique
</footer>

</body>
</html>
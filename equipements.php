
<?php
session_start();

require_once __DIR__ . '/config/connexion.php';
require_once __DIR__ . '/config/securite.php';

verifierConnexion();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function h($valeur): string
{
    return htmlspecialchars((string)($valeur ?? ''), ENT_QUOTES, 'UTF-8');
}

$message = '';
$erreur = '';
$action = $_POST['action'] ?? '';
$role = $_SESSION['role'] ?? 'consultation';

// Seuls ces rôles peuvent modifier le parc
$peutModifier = in_array(
    $role,
    ['administrateur', 'responsable_dsi', 'technicien'],
    true
);

// Traitement des opérations POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)) {
        $erreur = 'Formulaire expiré ou invalide. Rechargez la page.';
    } elseif (!$peutModifier) {
        $erreur = 'Vous n’avez pas les droits pour modifier les équipements.';
    } else {
        try {
            if ($action === 'ajouter' || $action === 'modifier') {
                $id = (int)($_POST['id'] ?? 0);
                $code = trim($_POST['code_inventaire'] ?? '');
                $designation = trim($_POST['designation'] ?? '');
                $categorie = trim($_POST['categorie'] ?? '');
                $marque = trim($_POST['marque'] ?? '');
                $serie = trim($_POST['numero_serie'] ?? '');
                $ip = trim($_POST['adresse_ip'] ?? '');
                $idSite = (int)($_POST['id_site'] ?? 0);
                $etat = $_POST['etat'] ?? 'fonctionnel';
                $dateAcquisition = $_POST['date_acquisition'] ?? '';

                $etatsAutorises = [
                    'fonctionnel',
                    'en_panne',
                    'en_maintenance',
                    'reforme'
                ];

                if ($code === '' || $designation === '') {
                    throw new RuntimeException(
                        'Le code inventaire et la désignation sont obligatoires.'
                    );
                }

                if (!in_array($etat, $etatsAutorises, true)) {
                    throw new RuntimeException('État de l’équipement invalide.');
                }

                if ($ip !== '' && !filter_var($ip, FILTER_VALIDATE_IP)) {
                    throw new RuntimeException('Adresse IP invalide.');
                }

                if ($dateAcquisition !== '' &&
                    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateAcquisition)) {
                    throw new RuntimeException('Date d’acquisition invalide.');
                }

                if ($idSite > 0) {
                    $checkSite = $pdo->prepare(
                        "SELECT COUNT(*) FROM sites WHERE id = ?"
                    );
                    $checkSite->execute([$idSite]);

                    if ((int)$checkSite->fetchColumn() === 0) {
                        throw new RuntimeException('Le site sélectionné n’existe pas.');
                    }
                } else {
                    $idSite = null;
                }

                if ($action === 'ajouter') {
                    $sql = "INSERT INTO equipements
                        (code_inventaire, designation, categorie, marque,
                         numero_serie, adresse_ip, id_site, etat, date_acquisition)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $code,
                        $designation,
                        $categorie ?: null,
                        $marque ?: null,
                        $serie ?: null,
                        $ip ?: null,
                        $idSite,
                        $etat,
                        $dateAcquisition ?: null
                    ]);

                    journaliser(
                        $pdo,
                        'AJOUT_EQUIPEMENT',
                        'Ajout de l’équipement ' . $code
                    );

                    $message = 'Équipement ajouté avec succès.';
                } else {
                    if ($id <= 0) {
                        throw new RuntimeException('Identifiant d’équipement invalide.');
                    }

                    $sql = "UPDATE equipements SET
                        code_inventaire = ?,
                        designation = ?,
                        categorie = ?,
                        marque = ?,
                        numero_serie = ?,
                        adresse_ip = ?,
                        id_site = ?,
                        etat = ?,
                        date_acquisition = ?
                        WHERE id = ?";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $code,
                        $designation,
                        $categorie ?: null,
                        $marque ?: null,
                        $serie ?: null,
                        $ip ?: null,
                        $idSite,
                        $etat,
                        $dateAcquisition ?: null,
                        $id
                    ]);

                    journaliser(
                        $pdo,
                        'MODIFICATION_EQUIPEMENT',
                        'Modification de l’équipement ' . $code
                    );

                    $message = 'Équipement modifié avec succès.';
                }
            } elseif ($action === 'supprimer') {
                if ($role !== 'administrateur') {
                    throw new RuntimeException(
                        'Seul un administrateur peut supprimer un équipement.'
                    );
                }

                $id = (int)($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new RuntimeException('Identifiant invalide.');
                }

                $stmt = $pdo->prepare(
                    "SELECT code_inventaire FROM equipements WHERE id = ?"
                );
                $stmt->execute([$id]);
                $equipement = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$equipement) {
                    throw new RuntimeException('Équipement introuvable.');
                }

                $stmt = $pdo->prepare(
                    "DELETE FROM equipements WHERE id = ?"
                );
                $stmt->execute([$id]);

                journaliser(
                    $pdo,
                    'SUPPRESSION_EQUIPEMENT',
                    'Suppression de l’équipement ' . $equipement['code_inventaire']
                );

                $message = 'Équipement supprimé.';
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $erreur = 'Ce code inventaire existe déjà ou cet équipement est lié à d’autres données.';
            } else {
                $erreur = 'Erreur lors de l’opération. Vérifiez les tables et les champs de la base.';
            }
        } catch (RuntimeException $e) {
            $erreur = $e->getMessage();
        }
    }
}

// Recharger le jeton après une opération POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Sites disponibles
$listeSites = [];
$equipements = [];
$edition = null;
$erreurChargement = '';

try {
    $listeSites = $pdo->query(
        "SELECT id, nom FROM sites ORDER BY nom"
    )->fetchAll(PDO::FETCH_ASSOC);

    // Filtres et recherche
    $recherche = trim($_GET['recherche'] ?? '');
    $filtreEtat = $_GET['etat'] ?? '';

    $sql = "SELECT e.*, s.nom AS site_nom
            FROM equipements e
            LEFT JOIN sites s ON s.id = e.id_site
            WHERE 1=1";

    $params = [];

    if ($recherche !== '') {
        $sql .= " AND (
            e.code_inventaire LIKE ?
            OR e.designation LIKE ?
            OR e.marque LIKE ?
            OR e.numero_serie LIKE ?
            OR e.adresse_ip LIKE ?
        )";

        $terme = '%' . $recherche . '%';
        array_push($params, $terme, $terme, $terme, $terme, $terme);
    }

    $etatsAutorises = [
        'fonctionnel',
        'en_panne',
        'en_maintenance',
        'reforme'
    ];

    if (in_array($filtreEtat, $etatsAutorises, true)) {
        $sql .= " AND e.etat = ?";
        $params[] = $filtreEtat;
    }

    $sql .= " ORDER BY e.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $equipements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Charger un équipement à modifier
    if (isset($_GET['modifier']) && $peutModifier) {
        $idEdition = (int)$_GET['modifier'];

        $stmt = $pdo->prepare(
            "SELECT * FROM equipements WHERE id = ?"
        );
        $stmt->execute([$idEdition]);
        $edition = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
} catch (PDOException $e) {
    $erreurChargement = 'Impossible de charger les équipements ou les sites. Vérifiez la structure des tables.';
}

$nombreTotal = count($equipements);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Parc informatique - DSI Trésor Mali</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fa;
            color: #263445;
        }

        header {
            background: #12345a;
            color: white;
            padding: 20px 28px;
        }

        header h1 { margin: 0 0 7px; font-size: 24px; }
        header p { margin: 0; }

        nav {
            background: #1d2939;
            padding: 12px 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        nav a {
            color: white;
            text-decoration: none;
            padding: 10px 12px;
            border-radius: 5px;
        }

        nav a:hover { background: #34465e; }

        main {
            padding: 25px;
            max-width: 1500px;
            margin: auto;
        }

        .panel {
            background: white;
            padding: 20px;
            border-radius: 9px;
            margin-bottom: 22px;
            box-shadow: 0 2px 8px #0000000d;
        }

        .message {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 6px;
            background: #e4f7e9;
            color: #176534;
        }

        .erreur {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 6px;
            background: #fdecec;
            color: #a61b1b;
        }

        .grille {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
        }

        label {
            display: block;
            margin: 10px 0 6px;
            font-weight: bold;
            font-size: 14px;
        }

        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            font-size: 14px;
        }

        .boutons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }

        .btn {
            display: inline-block;
            padding: 10px 14px;
            border: 0;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
            background: #2471a3;
            color: white;
            font-size: 14px;
        }

        .btn-danger { background: #b42318; }
        .btn-gris { background: #64748b; }
        .btn-vert { background: #16794b; }

        .table-conteneur { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        th, td {
            padding: 11px;
            text-align: left;
            border-bottom: 1px solid #e5eaf0;
            font-size: 13px;
        }

        th { background: #edf2f7; }

        .etat {
            display: inline-block;
            padding: 5px 7px;
            border-radius: 5px;
            background: #e7edf4;
            white-space: nowrap;
        }

        .etat-fonctionnel { background: #d8f3df; color: #176534; }
        .etat-en_panne { background: #fde2e2; color: #a61b1b; }
        .etat-en_maintenance { background: #fff0d0; color: #805600; }
        .etat-reforme { background: #e5e7eb; color: #374151; }

        .actions {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .actions form { margin: 0; }

        @media (max-width: 800px) {
            .grille { grid-template-columns: 1fr; }
            main { padding: 12px; }
        }
    </style>
</head>
<body>

<header>
    <h1>Gestion du parc informatique</h1>
    <p>DSI — Trésor du Mali</p>
</header>

<nav>
    <a href="index.php">Tableau de bord</a>
    <a href="equipements.php">Parc informatique</a>
    <a href="incidents.php">Incidents</a>
    <a href="interventions.php">Interventions</a>
    <a href="sites.php">Sites</a>
    <a href="utilisateurs.php">Utilisateurs</a>
    <a href="rapports.php">Rapports</a>
    <a href="deconnexion.php">Déconnexion</a>
</nav>

<main>

    <h2>Parc informatique</h2>

    <?php if ($message !== ''): ?>
        <div class="message"><?= h($message) ?></div>
    <?php endif; ?>

    <?php if ($erreur !== ''): ?>
        <div class="erreur"><?= h($erreur) ?></div>
    <?php endif; ?>

    <?php if ($erreurChargement !== ''): ?>
        <div class="erreur"><?= h($erreurChargement) ?></div>
    <?php endif; ?>

    <?php if ($peutModifier): ?>
    <section class="panel">
        <h3><?= $edition ? 'Modifier un équipement' : 'Ajouter un équipement' ?></h3>

        <form method="POST" action="equipements.php">
            <input type="hidden" name="csrf_token"
                   value="<?= h($_SESSION['csrf_token']) ?>">

            <input type="hidden" name="action"
                   value="<?= $edition ? 'modifier' : 'ajouter' ?>">

            <?php if ($edition): ?>
                <input type="hidden" name="id" value="<?= (int)$edition['id'] ?>">
            <?php endif; ?>

            <div class="grille">
                <div>
                    <label for="code_inventaire">Code inventaire *</label>
                    <input id="code_inventaire" name="code_inventaire"
                           maxlength="100" required
                           value="<?= h($edition['code_inventaire'] ?? '') ?>">
                </div>

                <div>
                    <label for="designation">Désignation *</label>
                    <input id="designation" name="designation"
                           maxlength="255" required
                           value="<?= h($edition['designation'] ?? '') ?>">
                </div>

                <div>
                    <label for="categorie">Catégorie</label>
                    <input id="categorie" name="categorie"
                           value="<?= h($edition['categorie'] ?? '') ?>">
                </div>

                <div>
                    <label for="marque">Marque</label>
                    <input id="marque" name="marque"
                           value="<?= h($edition['marque'] ?? '') ?>">
                </div>

                <div>
                    <label for="numero_serie">Numéro de série</label>
                    <input id="numero_serie" name="numero_serie"
                           value="<?= h($edition['numero_serie'] ?? '') ?>">
                </div>

                <div>
                    <label for="adresse_ip">Adresse IP</label>
                    <input id="adresse_ip" name="adresse_ip"
                           placeholder="Ex. 192.168.1.10"
                           value="<?= h($edition['adresse_ip'] ?? '') ?>">
                </div>

                <div>
                    <label for="id_site">Site</label>
                    <select id="id_site" name="id_site">
                        <option value="0">-- Aucun site --</option>
                        <?php foreach ($listeSites as $site): ?>
                            <option value="<?= (int)$site['id'] ?>"
                                <?= (int)($edition['id_site'] ?? 0) === (int)$site['id'] ? 'selected' : '' ?>>
                                <?= h($site['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="etat">État</label>
                    <select id="etat" name="etat">
                        <?php
                        $etats = [
                            'fonctionnel' => 'Fonctionnel',
                            'en_panne' => 'En panne',
                            'en_maintenance' => 'En maintenance',
                            'reforme' => 'Réformé'
                        ];
                        foreach ($etats as $valeur => $libelle):
                        ?>
                            <option value="<?= h($valeur) ?>"
                                <?= ($edition['etat'] ?? 'fonctionnel') === $valeur ? 'selected' : '' ?>>
                                <?= h($libelle) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="date_acquisition">Date d’acquisition</label>
                    <input type="date" id="date_acquisition"
                           name="date_acquisition"
                           value="<?= h($edition['date_acquisition'] ?? '') ?>">
                </div>
            </div>

            <div class="boutons">
                <button class="btn btn-vert" type="submit">
                    <?= $edition ? 'Enregistrer les modifications' : 'Ajouter l’équipement' ?>
                </button>

                <?php if ($edition): ?>
                    <a class="btn btn-gris" href="equipements.php">Annuler</a>
                <?php endif; ?>
            </div>
        </form>
    </section>
    <?php endif; ?>

    <section class="panel">
        <h3>Rechercher un équipement</h3>

        <form method="GET" action="equipements.php">
            <div class="grille">
                <div>
                    <label for="recherche">Mot-clé</label>
                    <input type="search" id="recherche" name="recherche"
                           placeholder="Code, désignation, marque, IP..."
                           value="<?= h($_GET['recherche'] ?? '') ?>">
                </div>

                <div>
                    <label for="filtre_etat">Filtrer par état</label>
                    <select id="filtre_etat" name="etat">
                        <option value="">Tous les états</option>
                        <?php foreach ($etats as $valeur => $libelle): ?>
                            <option value="<?= h($valeur) ?>"
                                <?= ($_GET['etat'] ?? '') === $valeur ? 'selected' : '' ?>>
                                <?= h($libelle) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="boutons">
                    <button class="btn" type="submit">Rechercher</button>
                    <a class="btn btn-gris" href="equipements.php">Réinitialiser</a>
                </div>
            </div>
        </form>
    </section>

    <section class="panel">
        <h3>Liste des équipements (<?= $nombreTotal ?> résultat(s))</h3>

        <div class="table-conteneur">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Code inventaire</th>
                        <th>Désignation</th>
                        <th>Catégorie</th>
                        <th>Marque</th>
                        <th>N° série</th>
                        <th>Adresse IP</th>
                        <th>Site</th>
                        <th>État</th>
                        <th>Date acquisition</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (!$equipements): ?>
                    <tr>
                        <td colspan="11">Aucun équipement trouvé.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($equipements as $e): ?>
                        <tr>
                            <td><?= (int)$e['id'] ?></td>
                            <td><?= h($e['code_inventaire']) ?></td>
                            <td><?= h($e['designation']) ?></td>
                            <td><?= h($e['categorie']) ?></td>
                            <td><?= h($e['marque']) ?></td>
                            <td><?= h($e['numero_serie']) ?></td>
                            <td><?= h($e['adresse_ip']) ?></td>
                            <td><?= h($e['site_nom'] ?? 'Non attribué') ?></td>
                            <td>
                                <span class="etat etat-<?= h($e['etat']) ?>">
                                    <?= h($etats[$e['etat']] ?? $e['etat']) ?>
                                </span>
                            </td>
                            <td><?= h($e['date_acquisition']) ?></td>
                            <td>
                                <div class="actions">
                                    <?php if ($peutModifier): ?>
                                        <a class="btn"
                                           href="equipements.php?modifier=<?= (int)$e['id'] ?>">
                                            Modifier
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($role === 'administrateur'): ?>
                                        <form method="POST" action="equipements.php"
                                              onsubmit="return confirm('Confirmer la suppression de cet équipement ?');">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= h($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="supprimer">
                                            <input type="hidden" name="id"
                                                   value="<?= (int)$e['id'] ?>">
                                            <button class="btn btn-danger" type="submit">
                                                Supprimer
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>
</body>
</html>

<?php
/**
 * Fichier d'initialisation de l'application
 * Inclut les fichiers nécessaires et initialise les variables globales
 */

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Définir l'encodage
header('Content-Type: text/html; charset=utf-8');

// Définir le fuseau horaire
date_default_timezone_set('Europe/Paris');

// Configurer les paramètres de localisation pour la France
setlocale(LC_TIME, 'fr_FR.utf8', 'fra');

// Inclure les fichiers requis
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Vérifier si la base de données est disponible
function checkDatabase() {
    global $db;
    
    try {
        // Vérifier la connexion à la base de données
        $db->query("SELECT 1");
        return true;
    } catch (PDOException $e) {
        // En cas d'erreur, enregistrer le message
        error_log("Erreur de connexion à la base de données: " . $e->getMessage());
        return false;
    }
}

// Récupérer les paramètres globaux de l'application depuis la base de données
function getAppParams() {
    global $db;
    
    $params = [];
    
    try {
        $stmt = $db->query("SELECT cle, valeur FROM parametres");
        while ($row = $stmt->fetch()) {
            $params[$row['cle']] = $row['valeur'];
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des paramètres: " . $e->getMessage());
    }
    
    return $params;
}

// Vérifier si la base de données est accessible
$dbAvailable = checkDatabase();

// Si la base de données est disponible, récupérer les paramètres
if ($dbAvailable) {
    $appParams = getAppParams();
} else {
    $appParams = [];
}

// Définir des constantes pour l'application
define('APP_NAME', 'RAM Crèche');
define('APP_VERSION', '1.0.0');
define('APP_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]");
define('DB_AVAILABLE', $dbAvailable);

// Récupérer le délai minimum d'inscription (24h par défaut)
define('INSCRIPTION_DELAI_MIN', $appParams['delai_inscription_min'] ?? 24);

// Heures d'ouverture et fermeture
define('HEURE_OUVERTURE', $appParams['heures_ouverture'] ?? '08:00');
define('HEURE_FERMETURE', $appParams['heures_fermeture'] ?? '18:30');
?>

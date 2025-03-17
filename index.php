<?php
session_start();
require_once 'includes/init.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['user_role'] : '';

// Déterminer la page à afficher
$page = isset($_GET['page']) ? $_GET['page'] : 'accueil';

// Pages qui nécessitent une authentification
$secured_pages = ['dashboard', 'planning', 'enfants', 'inscriptions', 'profil', 'admin'];

// Rediriger vers la connexion si l'utilisateur tente d'accéder à une page sécurisée sans être connecté
if (in_array($page, $secured_pages) && !$isLoggedIn) {
    header('Location: index.php?page=login');
    exit;
}

// Pages accessibles uniquement aux administrateurs
$admin_pages = ['admin', 'gestion-utilisateurs'];

// Rediriger si l'utilisateur n'a pas les droits nécessaires
if (in_array($page, $admin_pages) && $userRole !== 'admin') {
    header('Location: index.php?page=dashboard');
    exit;
}

// Inclure l'en-tête
include_once 'includes/header.php';

// Inclure la page demandée
switch ($page) {
    case 'accueil':
        include_once 'pages/accueil.php';
        break;
    case 'login':
        include_once 'pages/login.php';
        break;
    case 'register':
        include_once 'pages/register.php';
        break;
    case 'dashboard':
        include_once 'pages/dashboard.php';
        break;
    case 'planning':
        include_once 'pages/planning.php';
        break;
    case 'enfants':
        include_once 'pages/enfants.php';
        break;
    case 'inscriptions':
        include_once 'pages/inscriptions.php';
        break;
    case 'profil':
        include_once 'pages/profil.php';
        break;
    case 'admin':
        include_once 'pages/admin.php';
        break;
    case 'logout':
        include_once 'actions/logout.php';
        break;
    default:
        include_once 'pages/404.php';
}

// Inclure le pied de page
include_once 'includes/footer.php';
?>

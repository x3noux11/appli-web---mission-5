<?php
// Détruire la session
session_start();
session_unset();
session_destroy();

// Rediriger vers la page d'accueil
setFlashMessage('info', 'Vous avez été déconnecté avec succès.');
header('Location: index.php');
exit;
?>

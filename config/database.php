<?php
// Configuration de la base de données
$host = 'localhost';
$dbname = 'ram_creche';
$username = 'root';
$password = '';

try {
    // Création de l'objet PDO pour la connexion à la base de données
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    
    // Configuration des attributs PDO pour afficher les erreurs
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Configuration pour retourner les résultats sous forme de tableau associatif
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // En cas d'erreur de connexion, afficher un message d'erreur
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}
?>

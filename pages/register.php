<?php
// Initialisation des variables
$errors = [];
$success = false;
$nom = $prenom = $email = $telephone = $password = $password_confirm = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération des données du formulaire
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // Validation des données
    if (empty($nom)) {
        $errors[] = "Le nom est obligatoire.";
    }
    
    if (empty($prenom)) {
        $errors[] = "Le prénom est obligatoire.";
    }
    
    if (empty($email)) {
        $errors[] = "L'email est obligatoire.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'email n'est pas valide.";
    } else {
        // Vérifier si l'email existe déjà
        global $db;
        $query = "SELECT COUNT(*) FROM users WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->execute(['email' => $email]);
        
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Cette adresse email est déjà utilisée.";
        }
    }
    
    if (empty($telephone)) {
        $errors[] = "Le numéro de téléphone est obligatoire.";
    } elseif (!preg_match('/^[0-9]{10}$/', str_replace(' ', '', $telephone))) {
        $errors[] = "Le format du numéro de téléphone n'est pas valide.";
    }
    
    if (empty($password)) {
        $errors[] = "Le mot de passe est obligatoire.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Le mot de passe doit contenir au moins 8 caractères.";
    }
    
    if ($password !== $password_confirm) {
        $errors[] = "Les mots de passe ne correspondent pas.";
    }
    
    // Si pas d'erreurs, enregistrer l'utilisateur
    if (empty($errors)) {
        try {
            // Hashage du mot de passe
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insertion en base de données
            $query = "INSERT INTO users (nom, prenom, email, telephone, password, role, created_at)
                      VALUES (:nom, :prenom, :email, :telephone, :password, 'parent', NOW())";
            
            $stmt = $db->prepare($query);
            $result = $stmt->execute([
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'telephone' => $telephone,
                'password' => $passwordHash
            ]);
            
            if ($result) {
                // Récupérer l'ID de l'utilisateur nouvellement créé
                $userId = $db->lastInsertId();
                
                // Rediriger vers la page de connexion avec un message de succès
                setFlashMessage('success', 'Votre compte a été créé avec succès. Vous pouvez maintenant vous connecter.');
                header('Location: index.php?page=login');
                exit;
            } else {
                $errors[] = "Une erreur est survenue lors de l'inscription.";
            }
        } catch (PDOException $e) {
            $errors[] = "Erreur de base de données : " . $e->getMessage();
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Inscription</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo escape($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="index.php?page=register">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="nom" class="form-label">Nom *</label>
                            <input type="text" class="form-control" id="nom" name="nom" value="<?php echo escape($nom); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="prenom" class="form-label">Prénom *</label>
                            <input type="text" class="form-control" id="prenom" name="prenom" value="<?php echo escape($prenom); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Adresse e-mail *</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo escape($email); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="telephone" class="form-label">Téléphone *</label>
                        <input type="tel" class="form-control" id="telephone" name="telephone" value="<?php echo escape($telephone); ?>" 
                               placeholder="06 12 34 56 78" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Mot de passe *</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <small class="text-muted">Le mot de passe doit contenir au moins 8 caractères</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">Confirmer le mot de passe *</label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                    </div>
                    
                    <div class="form-text mb-3">
                        * Champs obligatoires
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="accept_terms" name="accept_terms" required>
                        <label class="form-check-label" for="accept_terms">
                            J'accepte les <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">conditions d'utilisation</a>
                        </label>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Créer un compte</button>
                    </div>
                </form>
            </div>
            <div class="card-footer bg-light">
                <div class="text-center">
                    Vous avez déjà un compte ? <a href="index.php?page=login">Connectez-vous</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour les conditions d'utilisation -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="termsModalLabel">Conditions d'utilisation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6>1. Acceptation des conditions</h6>
                <p>En vous inscrivant à ce service, vous acceptez les présentes conditions d'utilisation.</p>
                
                <h6>2. Description du service</h6>
                <p>RAM Crèche fournit une plateforme de gestion d'inscriptions pour les crèches et services de garde d'enfants.</p>
                
                <h6>3. Protection des données personnelles</h6>
                <p>Nous recueillons et traitons vos données personnelles conformément à notre politique de confidentialité.</p>
                
                <h6>4. Obligations des utilisateurs</h6>
                <p>En tant qu'utilisateur, vous vous engagez à fournir des informations exactes et à jour.</p>
                
                <h6>5. Limitation de responsabilité</h6>
                <p>RAM Crèche ne peut être tenu responsable des erreurs ou omissions dans les informations fournies.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

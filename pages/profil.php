<?php
// Vérifier que l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: index.php?page=login');
    exit;
}

// Récupérer les informations de l'utilisateur connecté
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Initialisation des variables
$errors = [];
$success = false;

try {
    global $db;
    
    // Récupération des données de l'utilisateur
    $query = "SELECT * FROM users WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        setFlashMessage('danger', 'Utilisateur non trouvé.');
        header('Location: index.php?page=dashboard');
        exit;
    }
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des informations utilisateur: " . $e->getMessage());
    setFlashMessage('danger', 'Une erreur est survenue. Veuillez réessayer plus tard.');
    header('Location: index.php?page=dashboard');
    exit;
}

// Traitement du formulaire de mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Mise à jour des informations personnelles
    if ($_POST['action'] === 'update_info') {
        // Récupération des données du formulaire
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        
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
            // Vérifier si l'email existe déjà (sauf si c'est le même que l'utilisateur actuel)
            $query = "SELECT COUNT(*) FROM users WHERE email = :email AND id != :id";
            $stmt = $db->prepare($query);
            $stmt->execute(['email' => $email, 'id' => $userId]);
            
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Cette adresse email est déjà utilisée par un autre compte.";
            }
        }
        
        if (empty($telephone)) {
            $errors[] = "Le numéro de téléphone est obligatoire.";
        } elseif (!preg_match('/^[0-9]{10}$/', str_replace(' ', '', $telephone))) {
            $errors[] = "Le format du numéro de téléphone n'est pas valide.";
        }
        
        // Si pas d'erreurs, mettre à jour les informations
        if (empty($errors)) {
            try {
                $query = "UPDATE users 
                          SET nom = :nom, prenom = :prenom, email = :email, telephone = :telephone, updated_at = NOW()
                          WHERE id = :id";
                $stmt = $db->prepare($query);
                $result = $stmt->execute([
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'email' => $email,
                    'telephone' => $telephone,
                    'id' => $userId
                ]);
                
                if ($result) {
                    // Mettre à jour les informations de session
                    $_SESSION['user_name'] = $prenom . ' ' . $nom;
                    $_SESSION['user_email'] = $email;
                    
                    setFlashMessage('success', 'Vos informations personnelles ont été mises à jour avec succès.');
                    $success = true;
                    
                    // Recharger les données de l'utilisateur
                    $stmt = $db->prepare($query);
                    $stmt->execute(['id' => $userId]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $errors[] = "Une erreur est survenue lors de la mise à jour de vos informations.";
                }
            } catch (PDOException $e) {
                $errors[] = "Erreur de base de données : " . $e->getMessage();
            }
        }
    }
    
    // Changement de mot de passe
    elseif ($_POST['action'] === 'change_password') {
        // Récupération des données du formulaire
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validation des données
        if (empty($currentPassword)) {
            $errors[] = "Le mot de passe actuel est obligatoire.";
        } else {
            // Vérifier que le mot de passe actuel est correct
            if (!password_verify($currentPassword, $user['password'])) {
                $errors[] = "Le mot de passe actuel est incorrect.";
            }
        }
        
        if (empty($newPassword)) {
            $errors[] = "Le nouveau mot de passe est obligatoire.";
        } elseif (strlen($newPassword) < 8) {
            $errors[] = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
        }
        
        if ($newPassword !== $confirmPassword) {
            $errors[] = "La confirmation du mot de passe ne correspond pas au nouveau mot de passe.";
        }
        
        // Si pas d'erreurs, mettre à jour le mot de passe
        if (empty($errors)) {
            try {
                // Hashage du nouveau mot de passe
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $query = "UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id";
                $stmt = $db->prepare($query);
                $result = $stmt->execute([
                    'password' => $passwordHash,
                    'id' => $userId
                ]);
                
                if ($result) {
                    setFlashMessage('success', 'Votre mot de passe a été modifié avec succès.');
                    $success = true;
                } else {
                    $errors[] = "Une erreur est survenue lors de la modification de votre mot de passe.";
                }
            } catch (PDOException $e) {
                $errors[] = "Erreur de base de données : " . $e->getMessage();
            }
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="mb-3">Mon profil</h1>
        <p class="lead">Gérez vos informations personnelles et vos paramètres de compte.</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo escape($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>Vos modifications ont été enregistrées avec succès.
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4 mb-4">
        <!-- Carte d'information utilisateur -->
        <div class="card shadow-sm h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Informations utilisateur</h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <div class="avatar-placeholder mb-3">
                        <i class="fas fa-user fa-4x text-muted"></i>
                    </div>
                    <h4><?php echo escape($user['prenom'] . ' ' . $user['nom']); ?></h4>
                    <p class="badge bg-<?php 
                        if ($user['role'] === 'admin') echo 'danger';
                        elseif ($user['role'] === 'personnel') echo 'warning';
                        else echo 'info';
                    ?>">
                        <?php 
                            if ($user['role'] === 'admin') echo 'Administrateur';
                            elseif ($user['role'] === 'personnel') echo 'Personnel';
                            else echo 'Parent';
                        ?>
                    </p>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-envelope me-2"></i>Email</span>
                        <span class="text-muted"><?php echo escape($user['email']); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-phone me-2"></i>Téléphone</span>
                        <span class="text-muted"><?php echo escape($user['telephone']); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-calendar-alt me-2"></i>Membre depuis</span>
                        <span class="text-muted">
                            <?php 
                            $date = new DateTime($user['created_at']);
                            echo $date->format('d/m/Y'); 
                            ?>
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Modifier mes informations</h5>
            </div>
            <div class="card-body">
                <form method="post" action="index.php?page=profil">
                    <input type="hidden" name="action" value="update_info">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="nom" class="form-label">Nom *</label>
                            <input type="text" class="form-control" id="nom" name="nom" 
                                   value="<?php echo escape($user['nom']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="prenom" class="form-label">Prénom *</label>
                            <input type="text" class="form-control" id="prenom" name="prenom" 
                                   value="<?php echo escape($user['prenom']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Adresse e-mail *</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo escape($user['email']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="telephone" class="form-label">Téléphone *</label>
                        <input type="tel" class="form-control" id="telephone" name="telephone" 
                               value="<?php echo escape($user['telephone']); ?>" 
                               placeholder="06 12 34 56 78" required>
                    </div>
                    
                    <div class="form-text mb-3">
                        * Champs obligatoires
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Changer mon mot de passe</h5>
            </div>
            <div class="card-body">
                <form method="post" action="index.php?page=profil">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Mot de passe actuel *</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Nouveau mot de passe *</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <div class="form-text">Le mot de passe doit contenir au moins 8 caractères</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe *</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <div class="form-text mb-3">
                        * Champs obligatoires
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-key me-2"></i>Changer mon mot de passe
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .avatar-placeholder {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background-color: #f8f9fa;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
    }
</style>

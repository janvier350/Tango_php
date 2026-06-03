<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow">
  <div class="container-fluid px-4">
    <a class="navbar-brand fw-bold" href="index.php">
        <i class="bi bi-safe2 me-2"></i>SISTEMA DE CAJA
    </a>
     <a class="navbar-brand fw-bold" href="index.php">
        <i class="bi bi-safe2 me-2"></i>USUARIO
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <div class="navbar-nav ms-auto align-items-center">
        
        
        
        <a class="nav-link px-3" href="index.php"><i class="bi bi-people me-1"></i> Home</a> 
        <?php if($_SESSION['user_rol'] == 2): ?>
        <a class="nav-link px-3" href="movimientos.php"><i class="bi bi-journal-text me-1"></i> Movimientos</a>
        <?php endif; ?>
        
        <?php if($_SESSION['user_rol'] == 3): ?>
        <a class="nav-link px-3" href="movimientos_revisor.php"><i class="bi bi-journal-text me-1"></i> Movimientos Revisor</a>
        <?php endif; ?>
       <!-- <a class="nav-link px-3" href="usuarios.php"><i class="bi bi-people me-1"></i> Usuarios</a> -->
        
        <?php if($_SESSION['user_rol'] == 4): ?>
         <a class="nav-link px-3" href="movimientos.php"><i class="bi bi-journal-text me-1"></i> Movimientos</a>
        <?php endif; ?>
        
        <?php if($_SESSION['user_rol'] == 1): ?>
    <li class="nav-item">
        <a class="nav-link" href="usuarios.php">Gestionar Usuarios</a>
    </li>
<?php endif; ?>
        <a class="nav-link px-3" href="cambiar_clave.php">
            <i class="bi bi-key me-1"></i> Cambiar Contraseña
        </a>
        <a class="nav-link px-3" href="informe.php">
            <i class="bi bi-file-earmark-bar-graph me-1"></i> Informe
        </a>
        <a class="nav-link text-danger fw-bold ms-lg-3 px-3" href="?logout=1">
            <i class="bi bi-box-arrow-right me-1"></i> Salir
        </a>

      </div>
    </div>
  </div>
</nav>
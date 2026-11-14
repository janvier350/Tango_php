<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow">
  <div class="container-fluid px-4">
    <a class="navbar-brand fw-bold" href="index.php">
        <i class="bi bi-safe2 me-2"></i>TANGO
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <div class="navbar-nav ms-auto align-items-center">
        
        
        
        <a class="nav-link px-3" href="index.php"><i class="bi bi-people me-1"></i> Home</a>

        <?php
        // Selector de "Caja activa": solo si el usuario puede operar más de una oficina.
        if (isset($conn)) {
            require_once 'multicaja.php';
            $mis_ofis = oficinas_de_usuario($conn, $_SESSION['user_id'] ?? 0);
            if (count($mis_ofis) > 1):
        ?>
        <div class="nav-item dropdown">
          <a class="nav-link dropdown-toggle px-3 text-warning fw-bold" href="#" role="button" data-bs-toggle="dropdown" title="Caja activa">
            <i class="bi bi-cash-stack me-1"></i>Caja: <?php echo htmlspecialchars($_SESSION['oficina'] ?? ''); ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><h6 class="dropdown-header">Cambiar caja activa</h6></li>
            <?php foreach ($mis_ofis as $o): $act = ((int)$o['ID_OFICINA'] === (int)($_SESSION['oficina_ID'] ?? 0)); ?>
            <li>
              <a class="dropdown-item <?php echo $act ? 'active' : ''; ?>" href="cambiar_caja.php?id_oficina=<?php echo (int)$o['ID_OFICINA']; ?>">
                <?php echo htmlspecialchars($o['OFICINA']); ?><?php echo $act ? ' <i class="bi bi-check-lg"></i>' : ''; ?>
              </a>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; } ?>
        <?php if($_SESSION['user_rol'] == 2): ?>
        <?php
        // El formulario simplificado (tipo Mensajería) se decide por FORM_SIMPLE.
        // Para sesiones iniciadas antes del cambio (sin la clave) se usa el flag
        // anterior oficina_independiente como respaldo.
        $usa_form_simple = isset($_SESSION['oficina_form_simple'])
            ? !empty($_SESSION['oficina_form_simple'])
            : !empty($_SESSION['oficina_independiente']);
        $mov_link = $usa_form_simple ? 'movimientos_mensajeria.php' : 'movimientos.php';
        ?>
        <a class="nav-link px-3" href="<?php echo $mov_link; ?>"><i class="bi bi-journal-text me-1"></i> Movimientos</a>
        <a class="nav-link px-3 text-warning fw-bold" href="arqueo.php"><i class="bi bi-calculator me-1"></i> Arqueo</a>
        <a class="nav-link px-3" href="glosario_inf_financiera.php"><i class="bi bi-journal-text me-1"></i> Glosario Inf. Financiera</a>
        <?php endif; ?>

        <?php if($_SESSION['user_rol'] == 3): ?>
        <a class="nav-link px-3" href="dashboard.php"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
        <a class="nav-link px-3 text-warning fw-bold" href="arqueo.php"><i class="bi bi-calculator me-1"></i> Arqueo</a>
        <?php endif; ?>
       <!-- <a class="nav-link px-3" href="usuarios.php"><i class="bi bi-people me-1"></i> Usuarios</a> -->
        
        <?php if($_SESSION['user_rol'] == 4): ?>
         <a class="nav-link px-3" href="movimientos.php"><i class="bi bi-journal-text me-1"></i> Movimientos</a>
        <?php endif; ?>

        <?php if($_SESSION['user_rol'] == 1 || $_SESSION['user_rol'] == 3 || $_SESSION['user_rol'] == 4): ?>
        <a class="nav-link px-3" href="auditoria.php"><i class="bi bi-shield-check me-1"></i> Auditoría</a>
        <a class="nav-link px-3" href="beneficiarios_duplicados.php" title="Limpiar beneficiarios duplicados"><i class="bi bi-people me-1"></i> Duplicados</a>
        <?php endif; ?>

        <?php if($_SESSION['user_rol'] == 1): ?>
    <li class="nav-item">
        <a class="nav-link" href="usuarios.php">Gestionar Usuarios</a>
    </li>
<?php endif; ?>
        <a class="nav-link px-3" href="cambiar_clave.php">
            <i class="bi bi-key me-1"></i> Cambiar Contraseña
        </a>
        <?php if($_SESSION['user_rol'] == 3): ?>
        <a class="nav-link px-3" href="informe.php">
            <i class="bi bi-file-earmark-bar-graph me-1"></i> Informe
        </a>
        <a class="nav-link px-3" href="glosario_inf_financiera.php">
            <i class="bi bi-journal-text me-1"></i> Glosario Inf. Financiera
        </a>
        <?php endif; ?>
        <a class="nav-link text-danger fw-bold ms-lg-3 px-3" href="?logout=1">
            <i class="bi bi-box-arrow-right me-1"></i> Salir
        </a>

      </div>
    </div>
  </div>
</nav>
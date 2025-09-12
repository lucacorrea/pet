<?php
// autoErp/public/layouts/sidebar.php
// Sidebar compartilhado — usa $menuAtivo definido na página chamadora

if (!isset($menuAtivo)) {
  $menuAtivo = ''; // fallback
}
?>

<aside class="sidebar sidebar-default sidebar-white sidebar-base navs-rounded-all <?= (!empty($sidebarMini) ? 'sidebar-mini' : '') ?>">

  <div class="sidebar-header d-flex align-items-center justify-content-start">
    <a href="./dashboard.php" class="navbar-brand">
      <div class="logo-main">
        <div class="logo-normal">
          <img src="./assets/images/auth/logo.png" alt="logo" class="logo-dashboard">
        </div>
      </div>
      <h4 class="logo-title title-dashboard">Mundo Pets</h4>
    </a>
    <div class="sidebar-toggle" data-toggle="sidebar" data-active="true">
      <i class="icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
          <path d="M4.25 12.2744L19.25 12.2744" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
          <path d="M10.2998 18.2988L4.2498 12.2748L10.2998 6.24976" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </i>
    </div>
  </div>

  <div class="sidebar-body pt-0 data-scrollbar">
    <div class="sidebar-list">
      <ul class="navbar-nav iq-main-menu" id="sidebar-menu">
        <!-- DASHBOARD -->
        <li class="nav-item">
          <a class="nav-link <?= ($menuAtivo === 'dashboard' ? 'active' : '') ?>" href="./dashboard.php">
            <i class="bi bi-grid icon"></i><span class="item-name">Dashboard</span>
          </a>
        </li>
        <li><hr class="hr-horizontal"></li>

        <!-- VENDAS -->
        <li class="nav-item">
          <a class="nav-link <?= ($menuAtivo === 'vendas-rapida' ? 'active' : '') ?>" href="./vendas/pages/vendaRapida.php">
            <i class="bi bi-cash-coin icon"></i><span class="item-name">Venda Rápida</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= ($menuAtivo === 'vendas-orcamentos' ? 'active' : '') ?>" href="./vendas/pages/orcamentos.php">
            <i class="bi bi-file-earmark-text icon"></i><span class="item-name">Orçamentos</span>
          </a>
        </li>

        <!-- CAIXA (agora dentro de /vendas/pages e com collapse próprio) -->
        <li class="nav-item">
          <a class="nav-link" data-bs-toggle="collapse" href="#sidebar-caixa" role="button"
             aria-expanded="<?= str_starts_with($menuAtivo, 'caixa-') ? 'true' : 'false' ?>"
             aria-controls="sidebar-caixa">
            <i class="bi bi-wallet2 icon"></i><span class="item-name">Caixa</span><i class="bi bi-chevron-right right-icon"></i>
          </a>
          <ul class="sub-nav collapse <?= str_starts_with($menuAtivo, 'caixa-') ? 'show' : '' ?>" id="sidebar-caixa" data-bs-parent="#sidebar-menu">
            <li class="nav-item">
              <a class="nav-link <?= ($menuAtivo === 'caixa-abrir' ? 'active' : '') ?>" href="./vendas/pages/caixaAbrir.php">
                <i class="bi bi-cash-stack icon"></i><span class="item-name">Abrir Caixa</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= ($menuAtivo === 'caixa-fechar' ? 'active' : '') ?>" href="./vendas/pages/caixaFechar.php">
                <i class="bi bi-lock icon"></i><span class="item-name">Fechar Caixa</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= ($menuAtivo === 'caixa-suprimento' ? 'active' : '') ?>" href="./vendas/pages/caixaSuprimento.php">
                <i class="bi bi-arrow-up-circle icon"></i><span class="item-name">Suprimento</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= ($menuAtivo === 'caixa-sangria' ? 'active' : '') ?>" href="./vendas/pages/caixaSangria.php">
                <i class="bi bi-arrow-down-circle icon"></i><span class="item-name">Sangria</span>
              </a>
            </li>
          </ul>
        </li>

        <!-- ESTOQUE -->
        <li class="nav-item">
          <a class="nav-link" data-bs-toggle="collapse" href="#sidebar-estoque" role="button"
             aria-expanded="<?= str_starts_with($menuAtivo, 'estoque-') ? 'true' : 'false' ?>"
             aria-controls="sidebar-estoque">
            <i class="bi bi-truck icon"></i><span class="item-name">Estoque</span><i class="bi bi-chevron-right right-icon"></i>
          </a>
          <ul class="sub-nav collapse <?= str_starts_with($menuAtivo, 'estoque-') ? 'show' : '' ?>" id="sidebar-estoque" data-bs-parent="#sidebar-menu">
            <li class="nav-item">
              <a class="nav-link <?= ($menuAtivo === 'estoque-lista' ? 'active' : '') ?>" href="./estoque/pages/estoque.php">
                <i class="bi bi-box icon"></i><span class="item-name">Lista Estoque</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= ($menuAtivo === 'estoque-produtos' ? 'active' : '') ?>" href="./estoque/pages/produtos.php">
                <i class="bi bi-gear icon"></i><span class="item-name">Lista Produtos</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= ($menuAtivo === 'estoque-fornecedores' ? 'active' : '') ?>" href="./estoque/pages/fornecedores.php">
                <i class="bi bi-person-check icon"></i><span class="item-name">Lista Fornecedores</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= ($menuAtivo === 'estoque-add-fornecedor' ? 'active' : '') ?>" href="./estoque/pages/fornecedoresNovo.php">
                <i class="bi bi-journal-text icon"></i><span class="item-name">Add Fornecedor</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= ($menuAtivo === 'estoque-add-produto' ? 'active' : '') ?>" href="./estoque/pages/produtosNovo.php">
                <i class="bi bi-arrow-down-circle icon"></i><span class="item-name">Add Produto</span>
              </a>
            </li>
          </ul>
        </li>

        <!-- RELATÓRIOS -->
        <li class="nav-item">
          <a class="nav-link" data-bs-toggle="collapse" href="#sidebar-relatorios" role="button"
             aria-expanded="<?= str_starts_with($menuAtivo, 'relatorios-') ? 'true' : 'false' ?>"
             aria-controls="sidebar-relatorios">
            <i class="bi bi-clipboard-data icon"></i><span class="item-name">Relatórios</span><i class="bi bi-chevron-right right-icon"></i>
          </a>
          <ul class="sub-nav collapse <?= str_starts_with($menuAtivo, 'relatorios-') ? 'show' : '' ?>" id="sidebar-relatorios" data-bs-parent="#sidebar-menu">
            <li class="nav-item">
              <a class="nav-link <?= ($menuAtivo === 'relatorios-vendas' ? 'active' : '') ?>" href="./vendas/pages/relatorioVendas.php">
                <i class="bi bi-bar-chart icon"></i><span class="item-name">Vendas</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= ($menuAtivo === 'relatorios-financeiro' ? 'active' : '') ?>" href="./vendas/pages/relatorioFinanceiro.php">
                <i class="bi bi-graph-up-arrow icon"></i><span class="item-name">Financeiro</span>
              </a>
            </li>
          </ul>
        </li>

        <!-- CONFIGURAÇÕES -->
        <li class="nav-item">
          <a class="nav-link" data-bs-toggle="collapse" href="#sidebar-config" role="button"
             aria-expanded="<?= str_starts_with($menuAtivo, 'config-') ? 'true' : 'false' ?>"
             aria-controls="sidebar-config">
            <i class="bi bi-gear icon"></i><span class="item-name">Configurações</span><i class="bi bi-chevron-right right-icon"></i>
          </a>
          <ul class="sub-nav collapse <?= str_starts_with($menuAtivo, 'config-') ? 'show' : '' ?>" id="sidebar-config" data-bs-parent="#sidebar-menu">
            <li class="nav-item">
              <a class="nav-link <?= ($menuAtivo === 'config-usuarios' ? 'active' : '') ?>" href="./configuracao/pages/listar.php">
                <i class="bi bi-people icon"></i><span class="item-name">Usuários</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= ($menuAtivo === 'config-add-usuario' ? 'active' : '') ?>" href="./configuracao/pages/novo.php">
                <i class="bi bi-person-plus icon"></i><span class="item-name">Add Usuários</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= ($menuAtivo === 'config-empresa' ? 'active' : '') ?>" href="./configuracao/pages/empresa.php">
                <i class="bi bi-building icon"></i><span class="item-name">Dados da Empresa</span>
              </a>
            </li>
          </ul>
        </li>

        <li><hr class="hr-horizontal"></li>
        <li class="nav-item">
          <a class="nav-link" href="./actions/logout.php">
            <i class="bi bi-box-arrow-right icon"></i><span class="item-name">Sair</span>
          </a>
        </li>
      </ul>
    </div>
  </div>
</aside>

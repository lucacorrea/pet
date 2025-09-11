// autoErp/public/assets/js/admin/cadastrarUsuario.js

(function () {
  const perfil = document.getElementById('perfil');
  const boxTipo = document.getElementById('box-tipo');
  const tipo = document.getElementById('tipo_funcionario');
  const cpf = document.querySelector('input[name="cpf"]');

  function syncTipoByPerfil() {
    if (!perfil || !boxTipo || !tipo) return;
    if (perfil.value === 'dono') {
      boxTipo.style.display = 'block';
      tipo.value = 'administrativo';
      tipo.setAttribute('disabled', 'disabled');
    } else {
      boxTipo.style.display = 'block';
      tipo.removeAttribute('disabled');
      if (!tipo.value) tipo.value = 'administrativo';
    }
  }

  if (perfil) {
    perfil.addEventListener('change', syncTipoByPerfil);
    syncTipoByPerfil();
  }

  if (cpf) {
    cpf.addEventListener('input', function () {
      this.value = this.value.replace(/\D+/g, '');
    });
  }
})();

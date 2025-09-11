// autoErp/public/assets/js/admin/solicitacao.js
(function () {
  // prompt de CNPJ quando faltar
  document.querySelectorAll('.sa-approve-form').forEach(function (form) {
    const btn = form.querySelector('.sa-approve-btn');
    if (!btn) return;

    form.addEventListener('submit', function (e) {
      const need = btn.getAttribute('data-need-cnpj') === '1';
      if (!need) return; // já tem CNPJ

      e.preventDefault();

      const empresa = btn.getAttribute('data-empresa') || 'a empresa';
      let cnpj = window.prompt('Informe o CNPJ para aprovar ' + empresa + ' (somente números):', '');
      if (!cnpj) return;

      cnpj = cnpj.replace(/\D+/g, '');
      if (cnpj.length !== 14) {
        alert('CNPJ inválido. Deve conter 14 dígitos.');
        return;
      }
      form.querySelector('input[name="cnpj"]').value = cnpj;
      form.submit();
    });
  });
})();

// Confirmação simples para ativar/inativar
document.querySelectorAll('.sa-toggle-form').forEach((form) => {
  form.addEventListener('submit', (e) => {
    const isAtivar = form.getAttribute('action')?.includes('empresaAtivar.php');
    const msg = isAtivar
      ? 'Ativar esta empresa?'
      : 'Inativar esta empresa?';
    if (!confirm(msg)) e.preventDefault();
  });
});

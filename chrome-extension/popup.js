document.addEventListener('DOMContentLoaded', function () {
  const urlInput = document.getElementById('wp_url');
  const userInput = document.getElementById('wp_user');
  const passInput = document.getElementById('wp_password');
  const saveBtn = document.getElementById('save_btn');
  const statusMsg = document.getElementById('status_msg');

  // Carrega configurações salvas
  chrome.storage.local.get(['wp_url', 'wp_user', 'wp_password'], function (data) {
    if (data.wp_url) urlInput.value = data.wp_url;
    if (data.wp_user) userInput.value = data.wp_user;
    if (data.wp_password) passInput.value = data.wp_password;
  });

  // Salva configurações
  saveBtn.addEventListener('click', function () {
    let url = urlInput.value.trim();
    const user = userInput.value.trim();
    const pass = passInput.value.trim();

    if (!url || !user || !pass) {
      statusMsg.className = 'status error';
      statusMsg.textContent = 'Por favor, preencha todos os campos.';
      return;
    }

    // Garante que a URL termina com barra
    if (!url.endsWith('/')) {
      url += '/';
    }

    chrome.storage.local.set({
      wp_url: url,
      wp_user: user,
      wp_password: pass
    }, function () {
      statusMsg.className = 'status success';
      statusMsg.textContent = 'Configurações salvas com sucesso!';
      setTimeout(() => {
        statusMsg.textContent = '';
      }, 2500);
    });
  });
});

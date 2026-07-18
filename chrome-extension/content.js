(function () {
  'use strict';

  // Configurações salvas pela extensão
  let wpConfig = null;

  // Carrega as credenciais do storage
  function loadConfig() {
    chrome.storage.local.get(['wp_url', 'wp_user', 'wp_password'], function (data) {
      if (data.wp_url && data.wp_user && data.wp_password) {
        wpConfig = data;
      } else {
        wpConfig = null;
      }
    });
  }

  // Carrega inicialmente e escuta mudanças no storage
  loadConfig();
  chrome.storage.onChanged.addListener(loadConfig);

  // Monitora o DOM do Gemini para detectar imagens geradas
  const observer = new MutationObserver(function () {
    findAndInjectButtons();
  });

  observer.observe(document.body, {
    childList: true,
    subtree: true
  });

  function findAndInjectButtons() {
    // Procura por imagens no chat do Gemini
    const images = document.querySelectorAll('img');

    images.forEach(function (img) {
      // Ignora imagens muito pequenas (como fotos de perfil, ícones, sparkles)
      if (img.naturalWidth > 0 && img.naturalWidth < 150) return;
      if (img.width > 0 && img.width < 150) return;

      // Verifica se a URL da imagem é do Google User Content (onde ficam as imagens geradas)
      const src = img.src || '';
      const isGoogleImage = src.includes('googleusercontent.com') || src.includes('gemini.gstatic.com') || src.startsWith('blob:');
      if (!isGoogleImage) return;

      // Acha o container ancestral para posicionar o botão de forma relativa
      // No Gemini, as imagens geralmente ficam dentro de uma div com classe de imagem ou similar
      const container = img.parentElement;
      if (!container) return;

      // Se já injetamos o botão neste container, ignora
      if (container.querySelector('.wpaip-gemini-btn-wrapper') || container.classList.contains('wpaip-has-btn')) {
        return;
      }

      // Adiciona posição relativa no container para apoiar o absolute do botão
      container.classList.add('wpaip-positioned-container', 'wpaip-has-btn');

      // Cria a barra flutuante de botões
      const btnWrapper = document.createElement('div');
      btnWrapper.className = 'wpaip-gemini-btn-wrapper';

      // Botão de upload
      const uploadBtn = document.createElement('button');
      uploadBtn.className = 'wpaip-gemini-btn';
      uploadBtn.innerHTML = '✦ Enviar ao WordPress';
      uploadBtn.type = 'button';

      uploadBtn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        if (!wpConfig) {
          alert('PostFácil IA: Por favor, clique no ícone da extensão no seu navegador e configure a URL do seu site WordPress e credenciais.');
          return;
        }

        if (uploadBtn.classList.contains('is-loading')) return;

        uploadBtn.classList.add('is-loading');
        uploadBtn.textContent = 'Enviando...';

        // Envia mensagem para o Service Worker (background.js) fazer o upload sem bloqueio de CORS
        chrome.runtime.sendMessage({
          action: 'upload_image_to_wp',
          src: src
        }, async function (response) {
          if (chrome.runtime.lastError) {
            alert('Erro de comunicação com a extensão: ' + chrome.runtime.lastError.message);
            resetButton();
            return;
          }

          if (response && response.success) {
            const data = response.data;
            const imageHtml = `<img src="${data.source_url}" alt="Imagem gerada no Gemini" class="aligncenter size-large wp-image-${data.id}" />`;

            await copyToClipboard(imageHtml);

            uploadBtn.className = 'wpaip-gemini-btn is-success';
            uploadBtn.textContent = 'Copiado (Ctrl+V)!';

            setTimeout(resetButton, 3500);
          } else {
            alert('Falha ao enviar imagem para o WordPress:\n' + (response.error || 'Erro desconhecido.'));
            resetButton();
          }
        });

        function resetButton() {
          uploadBtn.className = 'wpaip-gemini-btn';
          uploadBtn.innerHTML = '✦ Enviar ao WordPress';
        }
      });

      btnWrapper.appendChild(uploadBtn);
      container.appendChild(btnWrapper);
    });
  }

  // Copia texto para a área de transferência de forma compatível
  async function copyToClipboard(text) {
    try {
      await navigator.clipboard.writeText(text);
    } catch (err) {
      const textarea = document.createElement('textarea');
      textarea.value = text;
      textarea.style.position = 'fixed';
      textarea.style.opacity = '0';
      document.body.appendChild(textarea);
      textarea.focus();
      textarea.select();
      document.execCommand('copy');
      document.body.removeChild(textarea);
    }
  }

  // Roda uma verificação inicial rápida
  setTimeout(findAndInjectButtons, 2000);
})();

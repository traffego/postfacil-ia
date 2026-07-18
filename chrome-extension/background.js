/**
 * background.js
 * Service Worker da Extensão. Roda em segundo plano e possui privilégios de rede elevados,
 * permitindo realizar uploads para o WordPress sem bloqueios de CORS ou Mixed Content.
 */

chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
  if (request.action === 'upload_image_to_wp') {
    // Retornamos true para indicar que a resposta será assíncrona
    handleUpload(request.src)
      .then(result => sendResponse({ success: true, data: result }))
      .catch(error => sendResponse({ success: false, error: error.message }));
    return true;
  }
});

async function handleUpload(src) {
  // Carrega configurações salvas na extensão
  const data = await chrome.storage.local.get(['wp_url', 'wp_user', 'wp_password']);
  const wp_url = data.wp_url;
  const wp_user = data.wp_user;
  const wp_password = data.wp_password;

  if (!wp_url || !wp_user || !wp_password) {
    throw new Error('Configurações do WordPress não encontradas. Clique no ícone da extensão para configurar.');
  }

  // 1. Faz o fetch na URL do Google para pegar os bytes da imagem
  const imageResponse = await fetch(src);
  if (!imageResponse.ok) {
    throw new Error('Falha ao baixar imagem dos servidores do Google.');
  }
  
  const arrayBuffer = await imageResponse.arrayBuffer();
  const contentType = imageResponse.headers.get('Content-Type') || 'image/png';
  
  // Define extensão correta
  let ext = 'png';
  if (contentType === 'image/jpeg') ext = 'jpg';
  if (contentType === 'image/webp') ext = 'webp';
  const filename = 'gemini-img-' + Date.now() + '.' + ext;

  // 2. Faz o upload para a biblioteca de mídia do WordPress
  const wpApiUrl = wp_url + 'wp-json/wp/v2/media';
  
  const headers = new Headers();
  const authString = btoa(wp_user + ':' + wp_password);
  headers.append('Authorization', 'Basic ' + authString);
  headers.append('Content-Disposition', `attachment; filename="${filename}"`);
  headers.append('Content-Type', contentType);

  const wpResponse = await fetch(wpApiUrl, {
    method: 'POST',
    headers: headers,
    body: arrayBuffer // Envia os bytes brutos do ArrayBuffer
  });

  const resData = await wpResponse.json();

  if (!wpResponse.ok) {
    throw new Error(resData.message || 'Erro HTTP ' + wpResponse.status);
  }

  return {
    source_url: resData.source_url,
    id: resData.id
  };
}

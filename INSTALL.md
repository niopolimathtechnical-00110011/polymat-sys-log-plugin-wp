# Guia de Instalação do PolyMath® Sistema de Log

## Método 1: Envio do arquivo ZIP (recomendado)

1. Acesse o painel administrativo do WordPress.
2. Vá em **Plugins → Adicionar plugin**.
3. Clique no botão **Enviar plugin** (no topo da página).
4. Escolha o arquivo `polymath-sys-log-plugin-wp.zip` (ou o `.zip` gerado pelo script).
5. Clique em **Instalar agora** e, após a instalação, em **Ativar**.

## Método 2: Injeção manual do código PHP (via painel de hospedagem)

Caso seu painel de controle (cPanel, DirectAdmin, etc.) permita acesso aos arquivos:

1. Faça download do arquivo `polymath-sys-log.php` direto do GitHub ou do `.zip`.
2. Acesse o **Gerenciador de Arquivos** do seu hospedeiro.
3. Navegue até a pasta `/wp-content/plugins/` da sua instalação WordPress.
4. Crie uma nova pasta chamada `polymath-sys-log`.
5. Envie o arquivo `polymath-sys-log.php` para dentro dessa pasta.
6. No painel do WordPress, vá em **Plugins → Plugins instalados**.
7. Localize “PolyMath® Sistema de Log” e clique em **Ativar**.

## Método 3: Upload via FTP

1. Conecte-se ao seu servidor usando um cliente FTP (FileZilla, etc.).
2. Acesse a pasta `public_html/wp-content/plugins/` (ou o caminho da sua instalação).
3. Crie uma pasta `polymath-sys-log` e envie o arquivo `polymath-sys-log.php` para dentro dela.
4. Ative o plugin no painel administrativo do WordPress.

## Após a ativação

- O plugin começará a registrar os logs imediatamente.
- Acesse o painel administrativo em **Ferramentas → Logs de Acesso** para visualizar e pesquisar os registros.
- Configure o período de retenção (dias) na mesma tela.

## Atualização

Para atualizar, substitua o arquivo `polymath-sys-log.php` pela versão mais recente e, se necessário, desative e reative o plugin (a tabela do banco de dados é criada automaticamente na ativação, mas atualizações futuras podem exigir alterações – consulte o changelog).

## Solução de problemas

- **Nenhum log aparece:** verifique se o plugin está ativo e se houve algum acesso ao site após a ativação. Faça uma tentativa de login inválida para gerar um evento de teste.
- **Erro de permissão na tabela:** o plugin usa as mesmas credenciais do WordPress. Se houver erro, confirme que o usuário do banco tem privilégios `CREATE` e `INSERT`.
- **Geolocalização não funciona:** o plugin utiliza o serviço gratuito `ip-api.com` (limite de 45 requisições por minuto). IPs privados ou muito frequentes podem não retornar localização.

Para mais ajuda, abra uma *issue* no [GitHub](https://github.com/nio00110011/polymath-sys-log).

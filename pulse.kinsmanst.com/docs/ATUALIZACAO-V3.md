# Atualização v3 — portal, catálogo e white label

## Ordem obrigatória

1. Faça backup dos arquivos e do banco.
2. No phpMyAdmin, selecione o banco do Pulse.
3. Importe `database/migrations/002_catalogo_global_white_label.sql`.
4. Substitua as pastas `app` e `public_html`, preservando `public_html/uploads`.
5. Não substitua o `.env` existente.
6. Entre como administrador, depois como profissional e como aluno.

## Novidades

- página pública de entrada do software no endereço principal;
- portal inicial do aluno com dieta, treino e evolução conforme o plano;
- catálogo Kinsman com exercícios prontos;
- cópia de exercício padrão para o catálogo particular do profissional;
- personalização com logo, cor, WhatsApp, Instagram, mensagem e rodapé;
- estrutura única administrada pela Kinsman.

## Segurança após atualizar

- mantenha `APP_DEBUG=false`;
- não envie novamente `install.php` em uma instalação já concluída;
- exclua arquivos antigos e diagnósticos da pasta pública.

# Operação, backup e recuperação

## Backup diário

Configure o backup da conta no cPanel para incluir:

- a pasta `/home/SEU_USUARIO/pulse`;
- o banco MySQL do Pulse;
- retenção diária de 7 dias e mensal de pelo menos 3 meses.

Faça também um backup manual antes de qualquer atualização. Um backup só é confiável depois de um teste de restauração.

## Exportação manual do banco

No phpMyAdmin, selecione o banco, abra **Exportar**, escolha SQL e marque estrutura + dados. Guarde o arquivo fora do servidor.

## Restauração

1. coloque o site em manutenção;
2. restaure primeiro os arquivos;
3. restaure o banco pelo phpMyAdmin;
4. confira se o `.env` aponta para o banco restaurado;
5. teste login, prontuário, uma imagem, uma dieta e um treino;
6. retire a manutenção.

## Logs

Consulte **Métricas > Erros** no cPanel e os logs do domínio. Em produção, mantenha `APP_DEBUG=false`; detalhes de exceções não devem aparecer aos usuários.

## Atualização segura

1. gere backup completo;
2. compare alterações de banco;
3. envie os arquivos novos sem substituir `.env` nem `uploads`;
4. aplique apenas migrações SQL novas;
5. execute o checklist de publicação;
6. mantenha uma cópia do pacote anterior para retorno.

## Incidente de acesso

Em caso de suspeita:

1. bloqueie a conta afetada (`users.active=0`);
2. altere senhas do cPanel, MySQL e administradores;
3. encerre sessões removendo os arquivos de sessão pelo gerenciador do servidor ou reiniciando o serviço PHP;
4. examine `audit_logs` e logs HTTP;
5. restaure somente após identificar a origem;
6. documente o incidente e avalie obrigações previstas na LGPD.

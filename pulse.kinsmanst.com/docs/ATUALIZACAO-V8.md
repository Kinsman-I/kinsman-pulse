# Atualização V8 — treino, evolução, mensagens e logo

## Antes de enviar os arquivos

No phpMyAdmin, selecione o banco do Pulse e importe uma única vez:

1. `database/migrations/003_tempo_por_exercicio.sql` (se ainda não foi importado)
2. `database/migrations/004_mensagens_aluno.sql` (se ainda não foi importado)
3. `database/migrations/005_pausa_do_treino.sql` (novo nesta versão)

Se as migrações 003 e 004 já foram importadas com sucesso, importe apenas a 005.

## Envio pelo cPanel

Extraia o ZIP diretamente em:

`/home4/israe196/pulse.kinsmanst.com/`

Confirme que as pastas `app`, `database`, `docs`, `public_html` e `tests` ficaram diretamente dentro dessa pasta. Não substitua o arquivo `.env` e não apague `public_html/uploads`.

Depois, abra o sistema e pressione `Ctrl + F5`.

## O que testar

- aluno inicia, pausa, continua e finaliza o treino;
- abrir “Ver execução” mostra os três passos e as orientações;
- exercício concluído registra início e fim;
- “Posso te ajudar?” envia a mensagem para a tela inicial do profissional;
- evolução aceita peso, cintura, quadril e fotos;
- a logo oficial aparece quando o profissional não cadastrou uma logo própria.

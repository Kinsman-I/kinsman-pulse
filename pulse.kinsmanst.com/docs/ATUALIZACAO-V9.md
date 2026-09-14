# Atualização V9 — anamnese e cronômetro iniciado pelo aluno

## Banco de dados

No phpMyAdmin, selecione o banco do Kinsman Pulse e importe, nesta ordem:

1. `database/migrations/005_pausa_do_treino.sql` — somente se ainda não foi importado;
2. `database/migrations/006_anamnese_e_inicio_do_treino.sql` — novo nesta versão.

Não execute novamente uma migração que já foi concluída.

## Arquivos

Extraia o ZIP diretamente em:

`/home4/israe196/pulse.kinsmanst.com/`

Não substitua o arquivo `.env` e não exclua a pasta `public_html/uploads`.

## Comportamento esperado

- o aluno encontra “Minha anamnese” no menu;
- o profissional recebe um aviso na visão geral e acessa as respostas pela ficha do aluno;
- o treino abre em `00:00` com o botão “Inicializar”;
- “Pausar” congela o cronômetro;
- “Continuar” retoma do mesmo tempo;
- “Finalizar” encerra a sessão e um novo treino começa novamente em `00:00`;
- durante a espera ou pausa, não é possível marcar exercícios como concluídos.

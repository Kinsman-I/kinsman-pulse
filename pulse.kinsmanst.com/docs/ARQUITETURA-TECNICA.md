# Arquitetura técnica

## Stack

| Camada | Tecnologia | Responsabilidade |
|---|---|---|
| Interface | HTML, CSS responsivo e JavaScript | Painéis de administrador, profissional e aluno |
| Aplicação | PHP 8.2+ | Rotas, regras, validação, sessões e permissões |
| Dados | MySQL/MariaDB via PDO | Persistência com consultas preparadas |
| Arquivos | `public_html/uploads` | Logos, exercícios e evolução, sem execução de scripts |
| E-mail | `mail()` do PHP/cPanel | Link de redefinição de senha |

## Estrutura de pastas

```text
pulse/
├── .env                         configuração privada
├── app/
│   ├── bootstrap.php            ambiente, banco, sessão, CSRF e autorização
│   ├── actions.php              comandos e gravações
│   └── views.php                layout compartilhado, cabeçalho e rodapé
├── database/
│   └── schema.mysql.sql         criação completa das tabelas
├── docs/                        instalação e operação
└── public_html/
    ├── index.php                controlador e telas
    ├── install.php              instalador de uso único
    ├── assets/                  CSS e JavaScript
    └── uploads/                 imagens enviadas
```

## Perfis e acesso

| Recurso | Administrador | Profissional | Aluno |
|---|---:|---:|---:|
| Cadastrar profissional | Sim | Não | Não |
| Cadastrar aluno | Sim | Sim | Não |
| Alterar marca principal | Sim | Não | Não |
| Alterar a própria marca | Não aplicável | Sim | Não |
| Ver prontuário | Todos do tenant | Apenas vinculados | Próprios resultados simplificados |
| Atualizar dieta e treino | Todos do tenant | Apenas vinculados | Não |
| Atualizar peso | Via avaliação | Via avaliação | Sim |
| Iniciar/finalizar treino | Não | Não | Sim |

O isolamento é aplicado no servidor; ocultar um botão na interface não é considerado autorização.

## Banco de dados

| Grupo | Tabelas principais |
|---|---|
| Empresas e acesso | `tenants`, `users`, `password_resets`, `professionals`, `students` |
| Prontuário | `assessments`, `progress_photos` |
| Nutrição | `food_plans`, `meals` |
| Treinos | `exercise_catalog`, `workout_sheets`, `workout_items`, `workout_sessions`, `workout_logs` |
| Financeiro | `commercial_plans`, `payments` |
| Segurança | `audit_logs` |

As dietas e fichas usam `draft`, `published` e `archived`. Ao publicar uma substituição, a versão publicada anterior é arquivada; o histórico permanece no banco.

## Segurança implementada

- senhas com `password_hash()` e `password_verify()`;
- identificador de sessão regenerado no login;
- cookies `HttpOnly`, `SameSite=Lax` e `Secure` sob HTTPS;
- token CSRF em todas as gravações;
- PDO com consultas preparadas e emulação desativada;
- validação do vínculo profissional/aluno no servidor;
- reset de senha com token aleatório, hash no banco, expiração e uso único;
- validação de MIME, extensão gerada e limite de tamanho para imagens;
- trilha de auditoria para ações sensíveis;
- cabeçalhos básicos de segurança pelo `.htaccess`.

## Limites conhecidos e próximas integrações

- O financeiro possui tabelas e interface-base, mas o recebimento real precisa de gateway e webhook assinados.
- O envio de e-mail usa o serviço do cPanel; para maior entrega, pode ser trocado por SMTP autenticado/serviço transacional.
- Vídeos são cadastrados por URL. Hospedagem própria de vídeo deve usar armazenamento externo para não consumir a conta cPanel.
- Antes de armazenar dados reais de saúde, formalize base legal, consentimento, política de retenção, exportação e exclusão conforme a LGPD.

# Instalação do Kinsman Pulse no cPanel

## Requisitos

- PHP 8.2 ou 8.3;
- MySQL 8 ou MariaDB 10.5+;
- extensões PHP `pdo_mysql`, `mbstring`, `fileinfo`, `openssl` e `json`;
- certificado SSL ativo em `pulse.kinsmanst.com`;
- função de e-mail PHP habilitada para recuperação de senha.

## 1. Criar o subdomínio

No cPanel, abra **Domínios** e crie `pulse.kinsmanst.com`. Defina a raiz do documento como:

```text
/home/SEU_USUARIO/pulse/public_html
```

Essa estrutura mantém configuração, código interno e SQL fora da pasta pública.

No DNS, aponte `pulse` para o mesmo servidor do cPanel. Aguarde a propagação e emita o SSL antes de ativar o uso público.

## 2. Enviar os arquivos

Envie e descompacte o pacote em:

```text
/home/SEU_USUARIO/pulse
```

Confira se `index.php` ficou em `/home/SEU_USUARIO/pulse/public_html/index.php`, e não em uma pasta duplicada.

## 3. Criar o banco MySQL

Em **Bancos de dados MySQL**:

1. crie um banco, por exemplo `SEU_USUARIO_pulse`;
2. crie um usuário MySQL com senha longa e exclusiva;
3. vincule o usuário ao banco com todos os privilégios;
4. guarde o nome completo do banco e do usuário — o cPanel normalmente inclui o prefixo da conta.

## 4. Configurar o ambiente

Duplique `.env.example` como `.env`, na raiz `pulse`, e altere:

```dotenv
APP_URL="https://pulse.kinsmanst.com"
APP_ENV="production"
APP_DEBUG=false
APP_SETUP_KEY="uma-chave-aleatoria-com-40-ou-mais-caracteres"
DB_HOST="localhost"
DB_NAME="SEU_USUARIO_pulse"
DB_USER="SEU_USUARIO_pulse"
DB_PASSWORD="SENHA_DO_BANCO"
MAIL_FROM="nao-responda@kinsmanst.com"
```

O arquivo `.env` não deve ficar dentro de `public_html`, não deve ser enviado por e-mail e não deve ser incluído em backup público.

## 5. Permissões

Use, em geral:

- pastas: `755`;
- arquivos: `644`;
- `.env`: `600` ou `640`;
- `public_html/uploads`: `755`.

Nunca use `777`. O diretório de uploads possui proteção contra execução de PHP.

## 6. Criar tabelas e administrador

Abra no navegador:

```text
https://pulse.kinsmanst.com/install.php
```

Informe a `APP_SETUP_KEY`, a marca, o nome, o e-mail e a senha do administrador. O instalador cria as tabelas e a primeira conta com `password_hash()`.

Depois do sucesso, **exclua imediatamente**:

```text
public_html/install.php
```

Como alternativa, importe `database/schema.mysql.sql` pelo phpMyAdmin e crie o administrador com o instalador.

## 7. Testes de publicação

Execute esta sequência:

1. abra `/index.php?page=login` em uma janela anônima;
2. entre como administrador;
3. cadastre um profissional;
4. entre como profissional e altere logo/cor;
5. cadastre um aluno;
6. crie um exercício com foto;
7. abra a ficha do aluno, crie e publique um treino;
8. crie e publique uma dieta;
9. entre como aluno, confira dieta/treino e atualize o peso;
10. inicie e finalize um treino;
11. teste “Esqueci minha senha”.

Se o reset não chegar, verifique a entrega de e-mail do domínio no cPanel. O endereço `MAIL_FROM` deve existir ou estar autorizado no servidor.

## 8. Produção

- mantenha `APP_DEBUG=false`;
- ative autenticação de dois fatores na conta cPanel;
- programe backup diário de arquivos e banco;
- mantenha PHP e cPanel atualizados;
- revise os logs de acesso e erros;
- publique termos de uso, política de privacidade e consentimento para dados de saúde.

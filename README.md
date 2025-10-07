<p align="center">
  <img src="public/images/santacasa-horizontal-branco.svg" width="400" style="margin-bottom: 15px;">
  <img src="public/images/passagem-plantao.svg" alt="Passagem Plantão" width="350" style="display: block; margin: 0 auto;">
</p>

<h1 align="center">Sistema de Passagem de Plantão</h1>
<p align="center"><strong>Santa Casa de Porto Alegre</strong></p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-11.32.0-FF2D20?style=flat&logo=laravel&logoColor=white" alt="Laravel">
  <img src="https://img.shields.io/badge/PHP-8.2.18-777BB4?style=flat&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat&logo=mysql&logoColor=white" alt="MySQL">
  <img src="https://img.shields.io/badge/TailwindCSS-3.4.15-06B6D4?style=flat&logo=tailwindcss&logoColor=white" alt="TailwindCSS">
  <img src="https://img.shields.io/badge/Vite-4.0.0-646CFF?style=flat&logo=vite&logoColor=white" alt="Vite">
</p>

## 📋 Sumário

- [Sobre o Projeto](#-sobre-o-projeto)
- [Tecnologias](#-tecnologias)
- [Funcionalidades](#-funcionalidades)
- [Instalação](#-orientações-para-instalação)
- [Contatos](#-contatos)

## 🏥 Sobre o Projeto

O Sistema de Passagem de Plantão digitaliza e integra o processo de passagem de plantão nas unidades hospitalares da Santa Casa de Porto Alegre. Substituindo o tradicional processo em papel, o sistema centraliza e padroniza o registro da comunicação clínica entre turnos de trabalho, aumentando a segurança do cuidado e a rastreabilidade das informações.

### Modelo SBAR

O sistema implementa digitalmente o modelo SBAR (Situação, Background, Avaliação e Recomendação), estrutura amplamente adotada para comunicação clínica padronizada. As informações são organizadas visualmente em cards individuais por leito, proporcionando:

-  Sincronização em tempo real com o sistema Tasy para exibição de dados clínicos dos pacientes
-  Visualização centralizada de exames, medicações, agendamentos e observações relevantes
-  Armazenamento estruturado em banco de dados para análise histórica e auditoria
-  Padronização do processo de comunicação entre equipes de diferentes turnos

## 🔧 Tecnologias

### Core
- [Laravel Framework 11.32.0](https://laravel.com/docs/11.x)
- [PHP 8.2.18+](https://www.php.net/docs.php)
- [MySQL 8.0+](https://dev.mysql.com/doc/)
- [Vite 4.0.0+](https://vitejs.dev/guide/)
- [TailwindCSS 3.4.15](https://tailwindcss.com/)

### Pacotes adicionais
- [directorytree/ldaprecord-laravel ^3.3.5](https://ldaprecord.com/docs/laravel/v3) - Integração com Active Directory
- [yajra/laravel-oci8 ^11.6.2](https://github.com/yajra/laravel-oci8) - Conexão com Oracle Database
- [laravel/breeze ^2.2.5](https://github.com/laravel/breeze) - Scaffolding de autenticação
- [laravel/ui ^4.5.2](https://github.com/laravel/ui) - Componentes de interface
- [opcodesio/log-viewer ^3.12](https://log-viewer.opcodes.io/docs/3.x) - Visualização de logs
- [spatie/laravel-permission ^6.10.1](https://spatie.be/docs/laravel-permission/v6/introduction) - Gerenciamento de permissões

## ✨ Funcionalidades

O sistema oferece as seguintes funcionalidades principais:

### Integração e Gestão Clínica
- [Integração em tempo real com o sistema Tasy](#)
- [Visualização de dados clínicos por leito](#)
- [Implementação digital do modelo SBAR](#)
- [Interface de gerenciamento de passagens de plantão](#)
- [Dashboard de monitoramento por unidade](#)

### Autenticação e Usuários
- [Interface de login com AD (Active Directory)](#)
- [Interface de gerenciamento de usuários](#)
- [Interface de gerenciamento de perfis](#)
- [Validação de usuários ativos e inativos](#)

### Infraestrutura
- [Conexões com bases MySQL e Oracle](#)

### Interfaces Disponíveis
- Login
- Inicial/Dashboard
- Visualização por leito
- Histórico de plantões
- Páginas de erro (401, 403, 404, 500, 503)
- Gerenciamento de usuários
- Gerenciamento de perfis
- Visualização de logs da aplicação

## 🚀 Orientações para Instalação

Para instalar e configurar o sistema, siga as etapas abaixo:

### Pré-requisitos
- PHP 8.2.18 ou superior
- MySQL 8.0 ou superior
- Composer
- Node.js e NPM

### Passos para Instalação

1. **Clone o repositório**
   ```bash
   git clone [url-do-repositorio]
   cd passagem-plantao
   ```

2. **Instale as dependências**
   ```bash
   composer install
   npm install
   ```

3. **Configure o ambiente**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure as credenciais de banco de dados**
   - Edite o arquivo `.env` e configure as conexões:
     - MySQL para o banco de dados principal
     - Credenciais do Active Directory
     - Conexão com o Tasy

5. **Ajuste as permissões de diretórios**
   
   Para Apache:
   ```bash
   sudo chown -R www-data storage/
   sudo chown -R www-data bootstrap/cache
   ```
   
   Para Nginx:
   ```bash
   sudo chown -R nginx storage/
   sudo chown -R nginx bootstrap/cache
   ```

6. **Configure o servidor web**
   - Ajuste o arquivo de configuração de hosts
   - Reinicie o serviço:
     ```bash
     sudo service apache2 restart
     # ou
     sudo service nginx restart
     ```

7. **Prepare o banco de dados**
   - Crie o banco de dados
   - Execute as migrações:
     ```bash
     php artisan migrate
     ```

8. **Adicione o usuário inicial**
   - Adicione `USER_FIRST` no arquivo `.env` com um usuário válido do AD
   - Execute o seeder:
     ```bash
     sudo php artisan db:seed
     ```

9. **Compile os assets**
   ```bash
   npm run build
   ```

10. **Acesse a aplicação**
    - Agora você pode acessar o sistema com suas credenciais do AD

## 📞 Contatos

Para maiores informações, contate o time de Inovação:

📧 [inovacao@santacasa.org.br](mailto:inovacao@santacasa.org.br)

### Desenvolvedores

**🔗 [Caio Foti Pontes](https://github.com/caiofoti)**  
✉️ [caio.pontes@santacasa.org.br](mailto:caio.pontes@santacasa.org.br)  
🌐 [github/iscmpa-rs](https://github.com/iscmpa-rs)

---

<p align="center">
  <small>© 2025 Santa Casa de Porto Alegre. Todos os direitos reservados.</small>
</p>

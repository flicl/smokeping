## Visão geral

Este `admin.php` é uma página web para gerenciar, via navegador, os grupos e hosts definidos no arquivo `Targets` do SmokePing, sem precisar editar o arquivo na mão.[1][2]
O objetivo é facilitar adicionar e remover alvos de medição (jogos, sites, IPs de clientes, etc.) direto pelo browser, salvando as mudanças no arquivo de configuração do SmokePing.[3]

## Como acessar a página

- Copie o arquivo para `/usr/share/smokeping/www/admin.php` (ou já deixe salvo diretamente lá).  
- No navegador, acesse algo como:  
  `http://SEU_SERVIDOR/smokeping/admin.php`  
  (o caminho exato depende de como o SmokePing está publicado no seu Apache/Nginx, mas em instalações padrão o diretório web dele é justamente esse caminho em `/usr/share/smokeping/www`. )[3]

## Principais funcionalidades

Em geral, a página oferece:

- Listar os grupos e hosts já existentes no arquivo `Targets` (os mesmos blocos que você veria se abrisse o arquivo na mão).[2]
- Formulário para criar novos grupos/menus e hosts (nome do host, IP/DNS, título/descrição, etc.), gravando tudo estruturalmente correto no `Targets`.[1]
- Botões para remover grupos ou hosts selecionados, ajustando o arquivo de configuração e mantendo o formato que o SmokePing espera.[1]

## Backups automáticos

Antes de gravar qualquer alteração, o script cria um backup do arquivo `Targets` em algo como `/var/backups/smokeping/Targets.AAAAmmdd-HHMMSS.bak`.  
Se alguma edição der problema, basta restaurar o arquivo original copiando um desses backups de volta para `/etc/smokeping/config.d/Targets` e depois recarregar o serviço do SmokePing.[3]

## Cuidados e boas práticas

- Sempre teste mudanças em poucos hosts primeiro; depois verifique se o SmokePing recarrega sem erro (`systemctl status smokeping` ou comando equivalente na sua distro).[3]
- Evite deixar a página exposta publicamente na internet; o ideal é proteger com autenticação (por exemplo, basic auth no Apache/Nginx) ou restringir por IP, já que ela altera diretamente a configuração do monitoramento.[3]

[1](https://www.mankier.com/5/smokeping_config)
[2](https://manpages.ubuntu.com/manpages/noble/man5/smokeping_examples.5.html)
[3](https://wiki.archlinux.org/title/Smokeping)

# Adote Certo

Jogo web de simulação de cuidados com pets, desenvolvido em PHP e MySQL para hospedagem compartilhada (incluindo InfinityFree).

## Recursos do jogo

- Cadastro, login e salvamento da partida no MySQL.
- Adoção de cão, gato, coelho, hamster ou calopsita, com nome, variedade, pelagem e personalidade.
- Seis necessidades, progressão de tempo offline e fases de vida.
- Alimentação, água, carinho, descanso, higiene, brincadeiras, passeio para cães e treino por reforço positivo.
- Loja, inventário, moedas e trabalho com intervalo de 30 minutos.
- Clínica veterinária, vacinação e histórico de saúde.
- Memorial ao fim da expectativa de vida simulada, com opção de iniciar uma nova adoção.
- Layout responsivo para celular e computador.

## Publicar no InfinityFree

1. No painel do InfinityFree, crie uma conta de hospedagem e um banco MySQL.
2. Abra o phpMyAdmin desse banco e importe `database.sql`.
3. Edite `config.php` com host, nome do banco, usuário e senha mostrados no painel. Use os valores exatos do InfinityFree; eles não são os dados do login do painel.
4. Envie `index.php`, `functions.php`, `config.php`, `style.css`, `app.js`, `manifest.json`, `service-worker.js`, `offline.html` e a pasta `assets` para `htdocs`. O arquivo `database.sql` pode ficar no seu computador depois da importação.
5. Acesse o domínio. Não é necessário instalar dependências nem executar comandos no servidor.

O jogo inclui um manifesto e service worker para poder ser instalado como PWA. Para instalar, acesse o site por HTTPS e use a opção “Adicionar à tela inicial” ou “Instalar app” do navegador. As telas autenticadas não são guardadas no cache offline.

Requer PHP 8.0+ com PDO MySQL e MySQL/MariaDB. Não publique `config.php` nem compartilhe sua senha do banco; se ela já foi exposta, troque-a no painel do InfinityFree e atualize o arquivo.

## Rodar localmente

Crie um banco MySQL, importe `database.sql`, ajuste `config.php` e inicie o servidor PHP apontando para esta pasta:

```sh
php -S 127.0.0.1:8000
```

O relógio do jogo avança de forma acelerada: cada dia real equivale a aproximadamente um mês de idade no jogo. As barras de necessidades diminuem durante a ausência (até 7 dias de necessidades são aplicados de uma vez); a saúde cai gradualmente quando necessidades vitais permanecem muito baixas. A simulação é educativa e não substitui orientação veterinária.

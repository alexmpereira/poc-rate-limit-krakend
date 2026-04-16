# POC: Rate Limiting com KrakenD e PHP

Esta Prova de Conceito (POC) tem como objetivo demonstrar, na prática, o funcionamento de um mecanismo de Rate Limit utilizando o KrakenD como API Gateway e uma aplicação PHP nativa como serviço de backend.

---

## 1. O Conceito de Rate Limit

**Rate Limit** (ou Limite de Taxa) é uma estratégia arquitetural defensiva utilizada para controlar a quantidade de requisições que um cliente (identificado por IP, Token, etc.) pode fazer a uma API ou serviço dentro de um determinado período de tempo.

**Principais objetivos:**
* **Prevenção de Abusos e Ataques:** Mitiga ataques de negação de serviço (DDoS) e tentativas de força bruta.
* **Gestão de Recursos:** Evita que um único usuário consuma toda a capacidade de processamento do servidor (o problema do *noisy neighbor*).
* **Controle de Custos:** Fundamental em infraestruturas elásticas onde o aumento de tráfego gera aumento direto de custos.

Quando o limite é excedido, o servidor deve interromper o processamento da requisição e retornar o código de status HTTP `429 Too Many Requests`.

---

## 2. Introdução ao KrakenD

O **KrakenD** é um API Gateway de altíssimo desempenho (Ultra-High Performance) escrito em Go. Ele atua como um intermediário entre os clientes (front-end, apps) e os seus microsserviços.

Diferente de outros gateways que focam em gerenciamento pesado, o KrakenD é *stateless* e focado estritamente na manipulação de requisições, roteamento, agregação de dados e aplicação de políticas de QoS (Quality of Service), como o Rate Limit. Nesta POC, ele recebe a requisição na porta 8000, verifica a política de rate limit e, se permitida, encaminha para o contêiner PHP.

---

## 3. Como Executar o Ambiente

O ambiente foi totalmente conteinerizado utilizando o Docker para garantir isolamento e facilidade de execução local.

**Pré-requisitos:**
* Docker
* Docker Compose

**Passo a passo:**
1. Clone ou baixe os arquivos deste repositório.
2. Abra o terminal na raiz do projeto (onde está o arquivo `docker-compose.yml`).
3. Execute o comando para construir e subir os contêineres em segundo plano:
```bash
docker compose up -d --build
```

Verifique se os serviços estão rodando:

```bash
docker compose ps
```

Você terá dois serviços rodando:

- php-app: Acessível diretamente em http://localhost:8080/ (Acesso direto, sem rate limit).

- krakend: Acessível em http://localhost:8000/api/dados (Acesso mediado pelo Gateway com rate limit).

## Fluxo de Teste do Rate Limit

A configuração atual do KrakenD (krakend.json) permite um máximo de 2 requisições por segundo por IP ("client_max_rate": 2, "strategy": "ip").

Para testar o funcionamento, abra seu terminal e execute um laço de repetição fazendo requisições rápidas usando o curl. O parâmetro -i mostrará os cabeçalhos HTTP.

Comando de teste (Linux/Mac/Git Bash):

```bash
    while true; do curl -i -s http://localhost:8000/api/dados | grep HTTP; sleep 0.2; done
```

Resultado esperado:

Você verá que as duas primeiras requisições no segundo retornarão sucesso (HTTP/1.1 200 OK). A partir da terceira requisição no mesmo segundo, o KrakenD bloqueará a chamada antes mesmo de chegar no PHP, retornando o erro:
HTTP/1.1 429 Too Many Requests

Pressione Ctrl+C para parar o teste.

## 5. Alternativa: Rate Limit com Nginx

Caso o ecossistema não demande um API Gateway dedicado como o KrakenD, é muito comum implementar o Rate Limit diretamente na camada de web server ou proxy reverso utilizando o Nginx.

Abaixo está um exemplo de como seria o arquivo de configuração (nginx.conf) para obter o mesmo resultado:

```bash
http {
    # Define uma zona de memória de 10MB chamada 'mylimit'
    # e define a taxa máxima de 2 requisições por segundo por IP.
    limit_req_zone $binary_remote_addr zone=mylimit:10m rate=2r/s;

    server {
        listen 80;
        server_name localhost;

        location / {
            # Aplica a zona criada.
            # 'burst=5' permite enfileirar até 5 requisições extras antes de rejeitar.
            # 'nodelay' processa o burst imediatamente, sem adicionar atraso artificial.
            limit_req zone=mylimit burst=5 nodelay;

            # Encaminha para o backend PHP
            proxy_pass http://php-app:80;

            # Customiza a resposta de erro (Opcional, o padrão é 503, mudamos para o correto 429)
            limit_req_status 429;
        }
    }
}
```

O Nginx utiliza o algoritmo Leaky Bucket (Balde Furado) para fazer esse controle de forma extremamente eficiente a nível de infraestrutura.
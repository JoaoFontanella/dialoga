# Usa uma imagem oficial do PHP (versão CLI ou Apache, ajuste conforme sua necessidade)
FROM php:8.2-cli

# Instala dependências essenciais do sistema operacional
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Instala o Node.js (versão 20 LTS neste exemplo)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Instala o Composer (caso o seu projeto utilize PHP Composer)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Define o diretório de trabalho dentro do contêiner
WORKDIR /app

# Copia os arquivos do projeto para o contêiner
COPY . .

# Instala as dependências Node.js
RUN npm install

# Porta padrão que o Render expõe (ajuste se sua aplicação usar outra porta, ex: 3000)
EXPOSE 10000
ENV PORT=10000

# Comando para iniciar a aplicação (conforme definido no seu package.json)
CMD ["npm", "start"]
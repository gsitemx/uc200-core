FROM node:20

WORKDIR /app
COPY package.json tsconfig.json ./
RUN npm install
COPY public ./public
COPY src ./src
EXPOSE 3000
CMD ["npm", "start"]

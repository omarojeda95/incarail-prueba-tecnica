# Builds the Vue SPA and serves it, together with the API, from nginx.
FROM node:24-alpine AS spa
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ ./
ENV VITE_API_BASE_URL=/api
# Accesos rápidos a los usuarios de prueba en el login (solo para la demo).
ARG MOSTRAR_USUARIOS_PRUEBA=false
ENV VITE_MOSTRAR_USUARIOS_PRUEBA=$MOSTRAR_USUARIOS_PRUEBA
RUN npm run build

FROM nginx:1.29-alpine
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=spa /app/dist /usr/share/nginx/html

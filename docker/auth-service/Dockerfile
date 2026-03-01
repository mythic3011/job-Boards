# ── Stage 1: build frontend ─────────────────────────
FROM node:20-alpine AS frontend-builder

WORKDIR /build
COPY frontend/package.json ./
RUN npm install
COPY frontend/ ./
RUN npm run build
# produced assets in /build/dist

# ── Stage 2: production auth service ─────────────────────────────────────
FROM node:20-alpine AS production

WORKDIR /app

# install runtime deps
COPY package.json ./
RUN npm install --production

COPY index.js ./

# copy built frontend into public folder
COPY --from=frontend-builder /build/dist/ ./public/

EXPOSE 3000

RUN addgroup -S authsvc && adduser -S authsvc -G authsvc
USER authsvc

HEALTHCHECK --interval=10s --timeout=5s --retries=3 \
  CMD wget -qO- http://localhost:3000/health || exit 1

CMD ["node", "index.js"]
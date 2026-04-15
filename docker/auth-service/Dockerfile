# ── Stage 1: build frontend ─────────────────────────
FROM node:20-alpine AS frontend-builder

WORKDIR /build
COPY docker/auth-service/frontend/package.json docker/auth-service/frontend/package-lock.json ./
RUN npm ci
COPY docker/auth-service/frontend/ ./
RUN npm run build
# produced assets in /build/dist

# ── Stage 2: production auth service ─────────────────────────────────────
FROM node:20-alpine AS production

WORKDIR /app

# install runtime deps
COPY docker/auth-service/package.json docker/auth-service/package-lock.json ./
RUN npm ci --omit=dev

COPY docker/auth-service/index.js ./
COPY docker/auth-service/canonical-audit.js ./
COPY config/contracts/canonical-audit.v1.json ./config/contracts/canonical-audit.v1.json

# copy built frontend into public folder
COPY --from=frontend-builder /build/dist/ ./public/

EXPOSE 3000

RUN addgroup -S authsvc && adduser -S authsvc -G authsvc
USER authsvc

HEALTHCHECK --interval=10s --timeout=5s --retries=3 \
  CMD wget -qO- http://localhost:3000/health || exit 1

CMD ["node", "index.js"]

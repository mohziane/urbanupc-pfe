# MaCandidature — Image durcie (post-PACS R2, mai 2026)
#
# HISTORIQUE :
#   v1 (avril 2026) : vulnérabilité plantée pédagogiquement
#                     - run as root, docker-cli installé, /var/run/docker.sock monté
#   v2 (15/05/2026) : remédiation R2 appliquée
#                     - user non-root (UID 1001)
#                     - docker-cli retiré
#                     - read-only filesystem (forcé par compose)
#                     - cap_drop ALL + no-new-privileges
#                     - CIS Docker Benchmark §5.31 désormais respecté
#                     - MITRE ATT&CK T1611 (Escape to Host) neutralisé par construction

FROM node:20-alpine AS deps
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci --omit=dev --no-audit --no-fund

FROM node:20-alpine AS build
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci --no-audit --no-fund
COPY prisma ./prisma
RUN npx prisma generate

FROM node:20-alpine AS runtime
LABEL org.opencontainers.image.title="MaCandidature"
LABEL org.opencontainers.image.description="Candidate portal — hardened (PACS R2 applied)"
LABEL org.opencontainers.image.version="2.0"
WORKDIR /app

# OpenSSL (requis par Prisma) ; pas de docker-cli (vuln R2 retirée).
RUN apk add --no-cache openssl

# Création d'un utilisateur applicatif non-root (cohérent avec MaFormation : UID 1001)
RUN addgroup -g 1001 -S app && adduser -u 1001 -S app -G app

COPY --from=deps  --chown=app:app /app/node_modules ./node_modules
COPY --from=build --chown=app:app /app/node_modules/.prisma ./node_modules/.prisma
COPY --from=build --chown=app:app /app/node_modules/@prisma ./node_modules/@prisma
COPY --chown=app:app . .

RUN mkdir -p /app/uploads /app/logs && chown -R app:app /app

USER app

ENV NODE_ENV=production
ENV PORT=3000
EXPOSE 3000

HEALTHCHECK --interval=30s --timeout=3s --retries=3 \
  CMD wget -qO- http://127.0.0.1:3000/healthz || exit 1

CMD ["node", "src/server.js"]

# Multi-stage build — minimal final image, non-root, read-only friendly
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
LABEL org.opencontainers.image.title="MaFormation"
LABEL org.opencontainers.image.description="Student portal — secure by design"
WORKDIR /app

# Prisma 5.x needs libssl3 on alpine 3.20+ (openssl-3.x binary target).
# Without this the prisma query engine fails to load with libssl.so.1.1 errors.
RUN apk add --no-cache openssl

# Non-root user
RUN addgroup -g 1001 -S app && adduser -u 1001 -S app -G app

# Copy deps + generated prisma client (chown so the app user can write
# to /app/node_modules/@prisma/engines during ad-hoc migrations).
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

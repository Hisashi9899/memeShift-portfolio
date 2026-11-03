FROM node:20-alpine AS deps
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json* ./
RUN npm ci || npm install

FROM node:20-alpine AS builder
ARG NEXT_PUBLIC_API_URL=/api
ARG NEXT_PUBLIC_SANCTUM_URL=/api
ENV NEXT_PUBLIC_API_URL=$NEXT_PUBLIC_API_URL
ENV NEXT_PUBLIC_SANCTUM_URL=$NEXT_PUBLIC_SANCTUM_URL
WORKDIR /app
COPY shared/ /shared/
COPY frontend/ .
COPY --from=deps /app/node_modules ./node_modules
RUN mkdir -p public && npm run build

FROM node:20-alpine AS runner
WORKDIR /app
ENV NODE_ENV=production
COPY --from=builder /app/.next ./.next
COPY --from=builder /app/public ./public
COPY --from=builder /app/package.json ./package.json
COPY --from=deps /app/node_modules ./node_modules
COPY --from=builder /app/next.config.js ./next.config.js
EXPOSE 3000
CMD ["npm", "start"]

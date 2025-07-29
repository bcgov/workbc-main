# pgvector.Dockerfile
ARG POSTGRES_TAG
FROM wodby/postgres:${POSTGRES_TAG} AS pgvector-builder
ARG PGVECTOR_TAG
RUN apk add git
RUN apk add build-base
RUN apk add clang
RUN apk add llvm-dev
WORKDIR /home
RUN git clone --branch v${PGVECTOR_TAG} https://github.com/pgvector/pgvector.git
WORKDIR /home/pgvector
RUN make
RUN make install

FROM wodby/postgres:${POSTGRES_TAG}
COPY --from=pgvector-builder /usr/local/lib/postgresql/bitcode/vector.index.bc /usr/local/lib/postgresql/bitcode/vector.index.bc
COPY --from=pgvector-builder /usr/local/lib/postgresql/vector.so /usr/local/lib/postgresql/vector.so
COPY --from=pgvector-builder /usr/local/share/postgresql/extension /usr/local/share/postgresql/extension

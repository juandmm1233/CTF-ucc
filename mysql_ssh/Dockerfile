FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

RUN apt-get update && apt-get install -y --no-install-recommends \
        mariadb-server \
        mariadb-client \
        openssh-server \
        sudo \
        bash \
        ca-certificates \
        iproute2 \
        net-tools \
        tzdata \
        dos2unix \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /var/run/sshd \
    && sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication yes/' /etc/ssh/sshd_config \
    && sed -i 's/^#\?PermitRootLogin.*/PermitRootLogin no/' /etc/ssh/sshd_config \
    && sed -i 's/^#\?UsePAM.*/UsePAM yes/' /etc/ssh/sshd_config

RUN useradd -m -s /bin/bash admin \
    && echo 'admin:password123' | chpasswd \
    && adduser admin sudo \
    && echo 'admin ALL=(ALL) NOPASSWD:ALL' >> /etc/sudoers

RUN sed -i 's/^bind-address.*/bind-address = 0.0.0.0/' /etc/mysql/mariadb.conf.d/50-server.cnf || true

COPY init.sql /opt/init.sql
COPY entrypoint.sh /usr/local/bin/entrypoint.sh

RUN dos2unix /usr/local/bin/entrypoint.sh /opt/init.sql \
    && chmod +x /usr/local/bin/entrypoint.sh \
    && chmod 644 /opt/init.sql

EXPOSE 22 3306

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

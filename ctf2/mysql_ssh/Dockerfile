FROM debian:bookworm-slim

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

RUN apt-get update && apt-get install -y --no-install-recommends \
        default-mysql-server \
        default-mysql-client \
        openssh-server \
        supervisor \
        sudo \
        bash \
        ca-certificates \
        tzdata \
        dos2unix \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /var/run/sshd /var/log/supervisor \
    && sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication yes/' /etc/ssh/sshd_config \
    && sed -i 's/^#\?PermitRootLogin.*/PermitRootLogin no/' /etc/ssh/sshd_config \
    && sed -i 's/^#\?UsePAM.*/UsePAM yes/' /etc/ssh/sshd_config

RUN useradd -m -s /bin/bash admin \
    && echo 'admin:password123' | chpasswd \
    && adduser admin sudo \
    && echo 'admin ALL=(ALL) NOPASSWD:ALL' >> /etc/sudoers

RUN if [ -f /etc/mysql/mariadb.conf.d/50-server.cnf ]; then \
        sed -i 's/^bind-address.*/bind-address = 0.0.0.0/' /etc/mysql/mariadb.conf.d/50-server.cnf; \
    fi; \
    if [ -f /etc/mysql/mysql.conf.d/mysqld.cnf ]; then \
        sed -i 's/^bind-address.*/bind-address = 0.0.0.0/' /etc/mysql/mysql.conf.d/mysqld.cnf; \
    fi

COPY init.sql /opt/init.sql
COPY init.sh /usr/local/bin/init.sh
COPY supervisord.conf /etc/supervisor/conf.d/ctf.conf

RUN dos2unix /usr/local/bin/init.sh /opt/init.sql /etc/supervisor/conf.d/ctf.conf \
    && chmod +x /usr/local/bin/init.sh \
    && chmod 644 /opt/init.sql /etc/supervisor/conf.d/ctf.conf

EXPOSE 22 3306

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]

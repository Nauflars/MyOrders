# Worker Management Documentation

## Overview

Los workers consumen mensajes asíncronos de RabbitMQ para procesar:
- Sincronización de datos desde SAP
- Generación de embeddings
- Procesamiento de órdenes
- Cualquier operación asíncrona

## Opciones de Ejecución

### 1. Docker Compose (Recomendado)

El servicio `worker` está configurado en `docker-compose.yml`:

```bash
# Iniciar todos los servicios (incluye 2 workers)
docker compose up -d

# Ver logs de workers
docker compose logs -f worker

# Escalar workers
docker compose up -d --scale worker=4

# Detener workers
docker compose stop worker

# Reiniciar workers
docker compose restart worker
```

### 2. Manual (Para desarrollo/testing)

```bash
# Worker simple
docker exec myorders-php php bin/console messenger:consume async -vv

# Worker con límites
docker exec myorders-php php bin/console messenger:consume async \
    --time-limit=3600 \
    --memory-limit=512M \
    -vv

# Worker con límite de mensajes
docker exec myorders-php php bin/console messenger:consume async \
    --limit=100 \
    -vv
```

### 3. Script de Inicio

```bash
# Ejecutar con configuración por defecto
docker exec myorders-php sh bin/worker.sh

# Con variables de entorno personalizadas
docker exec myorders-php sh -c "WORKER_COUNT=4 TIME_LIMIT=7200 sh bin/worker.sh"
```

### 4. Supervisor (Producción)

Para producción, usa Supervisor para gestionar múltiples workers:

```bash
# Instalar supervisor en el contenedor PHP
docker exec myorders-php apk add supervisor

# Copiar configuración
docker cp docker/supervisor/supervisor.conf myorders-php:/etc/supervisor/conf.d/myorders-worker.conf

# Iniciar supervisor
docker exec myorders-php supervisord -c /etc/supervisor/supervisord.conf

# Gestionar workers
docker exec myorders-php supervisorctl status
docker exec myorders-php supervisorctl restart myorders-worker:*
docker exec myorders-php supervisorctl stop myorders-worker:*
```

## Configuración de Workers

### Variables de Entorno

```env
WORKER_COUNT=2           # Número de workers (docker-compose replicas)
TIME_LIMIT=3600          # Tiempo máximo de ejecución (segundos)
MEMORY_LIMIT=512M        # Límite de memoria
QUEUE=async              # Cola a consumir
```

### Parámetros del Worker

- `--time-limit=3600`: El worker se reinicia cada hora (previene memory leaks)
- `--memory-limit=512M`: Límite de memoria por worker
- `--limit=100`: Procesa 100 mensajes y termina
- `-vv`: Modo verbose (útil para debugging)
- `--queues=async,failed`: Consumir múltiples colas

## Monitoreo

### Ver Estadísticas

```bash
# Estado de las colas
docker exec myorders-php php bin/console messenger:stats

# Mensajes en la cola
docker exec myorders-php php bin/console messenger:stats async

# Mensajes fallidos
docker exec myorders-php php bin/console messenger:failed:show
```

### Logs

```bash
# Logs de workers (Docker Compose)
docker compose logs -f worker

# Logs del contenedor PHP
docker logs -f myorders-php

# Logs de RabbitMQ
docker logs -f myorders-rabbitmq

# Archivo de log (si usa Supervisor)
docker exec myorders-php tail -f /var/www/html/var/log/worker.log
```

## Gestión de Mensajes Fallidos

### Ver Mensajes Fallidos

```bash
docker exec myorders-php php bin/console messenger:failed:show
```

### Reintentar Mensajes Fallidos

```bash
# Reintentar todos
docker exec myorders-php php bin/console messenger:failed:retry

# Reintentar específico
docker exec myorders-php php bin/console messenger:failed:retry 123

# Reintentar con límite
docker exec myorders-php php bin/console messenger:failed:retry --max=10
```

### Eliminar Mensajes Fallidos

```bash
# Eliminar todos
docker exec myorders-php php bin/console messenger:failed:remove

# Eliminar específico
docker exec myorders-php php bin/console messenger:failed:remove 123
```

## Detener Workers Gracefully

```bash
# Docker Compose
docker compose stop worker

# Enviar señal TERM (graceful shutdown)
docker exec myorders-php php bin/console messenger:stop-workers

# Forzar detención
docker kill -s SIGKILL myorders-worker
```

## Troubleshooting

### Worker no procesa mensajes

1. Verificar que RabbitMQ esté corriendo:
   ```bash
   docker ps | grep rabbitmq
   ```

2. Verificar conexión:
   ```bash
   docker exec myorders-php php bin/console messenger:stats
   ```

3. Verificar mensajes en cola:
   ```bash
   # RabbitMQ Management UI
   open http://localhost:15672
   # user: guest / pass: guest
   ```

### Worker consume mucha memoria

1. Reducir `--memory-limit`:
   ```bash
   --memory-limit=256M
   ```

2. Reducir `--time-limit`:
   ```bash
   --time-limit=1800  # 30 minutos
   ```

3. Usar `--limit` para procesar menos mensajes:
   ```bash
   --limit=50
   ```

### Mensajes se procesan múltiples veces

1. Verificar que no haya múltiples workers procesando la misma cola
2. Revisar logs para errores de handlers
3. Verificar tiempo de processing (timeout)

## Best Practices

1. **Producción**: Usar Supervisor o systemd para gestionar workers
2. **Desarrollo**: Usar Docker Compose con 1-2 workers
3. **Testing**: Ejecutar worker manualmente con `-vv` para ver logs detallados
4. **Escalar**: Aumentar workers según carga (monitorear con `messenger:stats`)
5. **Memoria**: Reiniciar workers periódicamente (--time-limit) para liberar memoria
6. **Logs**: Rotar logs periódicamente para evitar discos llenos

## Referencias

- [Symfony Messenger Docs](https://symfony.com/doc/current/messenger.html)
- [RabbitMQ Management](http://localhost:15672)
- [Docker Compose Scaling](https://docs.docker.com/compose/scale/)

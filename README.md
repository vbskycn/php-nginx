# Docker PHP-FPM 8.4 & Nginx 1.26 基于 Alpine Linux

基于 [Alpine Linux](https://www.alpinelinux.org/) 构建的 Docker PHP-FPM 8.4 & Nginx 1.26 容器镜像示例。

仓库地址: https://github.com/vbskycn/php-nginx

## 特性

* 基于轻量级且安全的 Alpine Linux 发行版构建
* 多平台支持，支持 AMD64, ARMv6, ARMv7, ARM64
* 极小的 Docker 镜像大小（约40MB）
* 使用 PHP 8.4 以获得最佳性能、低CPU使用率和内存占用
* **环境变量配置系统** - 支持通过环境变量灵活配置，适应不同设备规格
* 针对512M VPS优化，支持20个并发用户，可扩展到更大规格服务器
* 优化为仅在流量时使用资源（通过使用PHP-FPM的`ondemand`进程管理器）
* Nginx、PHP-FPM、Redis和supervisord服务在非特权用户（nobody）下运行，更加安全
* 所有服务的日志都重定向到Docker容器的输出（可通过`docker logs -f <容器名称>`查看）
* 自动服务管理和故障恢复，任何服务崩溃都会自动重启
* 遵循KISS原则（Keep It Simple, Stupid），易于理解和调整镜像以满足您的需求

[![Docker Pulls](https://img.shields.io/docker/pulls/zhoujie218/php-nginx.svg)](https://hub.docker.com/r/zhoujie218/php-nginx/)
![nginx 1.26](https://img.shields.io/badge/nginx-1.26-brightgreen.svg)
![php 8.4](https://img.shields.io/badge/php-8.4-brightgreen.svg)
![License MIT](https://img.shields.io/badge/license-MIT-blue.svg)

## 项目目标

这个容器镜像的目标是提供一个在容器中运行Nginx和PHP-FPM的示例，专门针对512M VPS进行优化，遵循最佳实践，易于理解和修改以满足您的需求。特别适合资源受限的小型服务器环境。如你需要可以自行调整并重新编译

## 环境变量配置系统

### 核心优势
* **灵活配置**：支持通过环境变量自定义所有关键参数
* **多设备适配**：从512M VPS到4G+服务器的完整配置方案
* **向后兼容**：不设置环境变量时使用512M VPS默认优化配置
* **实时生效**：配置在容器启动时动态生成，无需重新构建镜像

### 512M VPS 默认配置
* **内存优化**：PHP内存限制64MB，OPcache内存32MB，Redis内存限制64MB
* **进程管理**：PHP-FPM使用`ondemand`模式，按需创建进程，空闲时自动回收
* **并发控制**：最大20个PHP-FPM进程，支持20个并发用户
* **缓存策略**：启用OPcache加速，Redis LRU淘汰策略，静态资源5天缓存
* **轻量级基础**：基于Alpine Linux，镜像大小仅约40MB
* **资源监控**：所有服务日志统一输出，便于监控和调试

## 快速开始

### 基本使用

启动Docker容器：（不加变量为默认优化1H512M机器，直接使用就行）

```bash
docker run -p 80:8080 zhoujie218/php-nginx:latest
```

访问以下地址：
- **首页信息**: http://localhost

### 挂载自定义代码

```bash
docker run -p 80:8080 -v ~/my-codebase:/var/www/html zhoujie218/php-nginx:latest
```

### 使用Docker Compose

创建 `docker-compose.yml`：

```yaml
version: '3.8'
services:
  php-nginx:
    image: zhoujie218/php-nginx:latest
    ports:
      - "80:8080"
    volumes:
      - ./src:/var/www/html
    environment:
      - PHP_MEMORY_LIMIT=128M
    restart: unless-stopped
```

启动服务：

```bash
docker-compose up -d
```

### 环境变量配置

支持通过环境变量自定义配置，适应不同设备规格。如果不设置环境变量，将使用512M VPS的默认优化配置。

#### 核心环境变量
| 变量名 | 默认值 | 说明 |
|--------|--------|------|
| `PHP_MEMORY_LIMIT` | 64M | PHP内存限制 |
| `PHP_FPM_PM_MODE` | ondemand | PHP-FPM进程管理模式 |
| `PHP_FPM_MAX_CHILDREN` | 20 | 最大子进程数 |
| `OPCACHE_ENABLE` | 1 | 是否启用OPcache |
| `OPCACHE_MEMORY_CONSUMPTION` | 32 | OPcache内存大小(MB) |
| `REDIS_MAXMEMORY` | 64mb | Redis最大内存 |
| `NGINX_WORKER_PROCESSES` | auto | Nginx工作进程数 |

> 完整的环境变量配置说明请参考 [环境变量配置指南](docs/环境变量配置指南.md)

#### 使用示例

**1G内存服务器配置示例：**
```yaml
version: '3.8'
services:
  php-nginx:
    image: zhoujie218/php-nginx:latest
    ports:
      - "80:8080"
    volumes:
      - ./src:/var/www/html
    environment:
      - PHP_MEMORY_LIMIT=128M
      - OPCACHE_MEMORY_CONSUMPTION=64
      - PHP_FPM_MAX_CHILDREN=40
      - REDIS_MAXMEMORY=128mb
      - NGINX_WORKER_PROCESSES=2
    restart: unless-stopped
```

**2G内存服务器配置示例：**
```yaml
version: '3.8'
services:
  php-nginx:
    image: zhoujie218/php-nginx:latest
    ports:
      - "80:8080"
    volumes:
      - ./src:/var/www/html
    environment:
      - PHP_MEMORY_LIMIT=256M
      - OPCACHE_MEMORY_CONSUMPTION=128
      - PHP_FPM_MAX_CHILDREN=60
      - REDIS_MAXMEMORY=256mb
      - NGINX_WORKER_PROCESSES=2
    restart: unless-stopped
```

**小内存机器配置示例：**
```bash
docker stop php-nginx
docker rm php-nginx

docker run -d \
  --name php-nginx \
  --restart=always \
  -p 80:8080 \
  -e PHP_MEMORY_LIMIT=64M \
  -e PHP_DISPLAY_ERRORS=Off \
  -e PHP_MAX_EXECUTION_TIME=30 \
  -e OPCACHE_ENABLE=1 \
  -e OPCACHE_MEMORY_CONSUMPTION=32 \
  -e PHP_FPM_PM_MODE=ondemand \
  -e PHP_FPM_MAX_CHILDREN=20 \
  -e REDIS_MAXMEMORY=64mb \
  -e NGINX_WORKER_PROCESSES=1 \
  -e NGINX_WORKER_CONNECTIONS=1024 \
  zhoujie218/php-nginx:latest
```



## 版本管理

主要或次要更改总是作为[发布版本](https://github.com/vbskycn/php-nginx/releases)发布，并附有相应的变更日志。
当前版本：`1.1.47`
`latest`标签每周自动更新，包含Alpine Linux的最新补丁。

## 配置

本项目支持两种配置方式：**环境变量配置**（推荐）和**配置文件挂载**。

### 方式一：环境变量配置（推荐）

通过环境变量可以灵活配置所有关键参数，无需修改配置文件：

```bash
# 基本配置示例
docker run -d \
  --name php-nginx \
  -p 80:8080 \
  -e PHP_MEMORY_LIMIT=128M \
  -e PHP_FPM_MAX_CHILDREN=40 \
  -e OPCACHE_MEMORY_CONSUMPTION=64 \
  -e REDIS_MAXMEMORY=128mb \
  zhoujie218/php-nginx:latest
```

**优势**：
- ✅ 无需创建配置文件
- ✅ 配置在容器启动时动态生成
- ✅ 支持不同环境使用不同配置
- ✅ 配置参数完整，覆盖所有服务

> 详细的环境变量配置说明请参考 [环境变量配置指南](docs/环境变量配置指南.md)

### 方式二：配置文件挂载

如果需要自定义配置文件，可以通过挂载方式覆盖默认配置：

#### Nginx配置示例

创建 `nginx-custom.conf`：
```nginx
server {
    listen 8080;
    server_name localhost;
    root /var/www/html;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

挂载配置：
```bash
# Linux/macOS
docker run -v "$(pwd)/nginx-custom.conf:/etc/nginx/conf.d/default.conf" zhoujie218/php-nginx:latest

# Windows PowerShell
docker run -v "${PWD}/nginx-custom.conf:/etc/nginx/conf.d/default.conf" zhoujie218/php-nginx:latest
```

#### PHP配置示例

创建 `php-custom.ini`：
```ini
; 自定义PHP配置
memory_limit = 256M
max_execution_time = 60
upload_max_filesize = 10M
post_max_size = 10M
```

挂载配置：
```bash
# Linux/macOS
docker run -v "$(pwd)/php-custom.ini:/etc/php84/conf.d/99-custom.ini" zhoujie218/php-nginx:latest

# Windows PowerShell
docker run -v "${PWD}/php-custom.ini:/etc/php84/conf.d/99-custom.ini" zhoujie218/php-nginx:latest
```

#### PHP-FPM配置示例

创建 `php-fpm-custom.conf`：
```ini
[www]
pm = dynamic
pm.max_children = 100
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
```

挂载配置：
```bash
# Linux/macOS
docker run -v "$(pwd)/php-fpm-custom.conf:/etc/php84/php-fpm.d/www.conf" zhoujie218/php-nginx:latest

# Windows PowerShell
docker run -v "${PWD}/php-fpm-custom.conf:/etc/php84/php-fpm.d/www.conf" zhoujie218/php-nginx:latest
```

### 配置优先级

1. **环境变量配置** > **挂载配置文件** > **默认配置**
2. 环境变量配置会在容器启动时覆盖默认配置
3. 挂载的配置文件会覆盖默认配置文件

### 注意事项

- 配置文件挂载需要绝对路径
- 建议优先使用环境变量配置，更灵活且易于管理
- 配置文件修改后需要重启容器才能生效
- 环境变量配置在容器启动时自动生成，无需重启



## API文档

### Redis管理API

#### 获取键信息
```bash
GET /redis.php?action=get_key_info&key={key_name}
```

#### 删除键
```bash
GET /redis.php?action=delete_key&key={key_name}
```

#### 系统状态
```bash
GET /admin.php
```

### 健康检查端点

- **PHP-FPM状态**: `/fpm-status`
- **PHP-FPM Ping**: `/fpm-ping`

## 文档和示例

要修改此容器以满足您的特定需求，请查看以下文档：

* [🔧 技术文档](docs/技术文档.md) - 技术栈介绍、配置说明、扩展功能
* [🚀 部署指南](docs/部署指南.md) - 工作流触发、版本管理、镜像使用
* [📖 项目指南](docs/项目指南.md) - 贡献指南、开发指南、代码规范
* [💡 使用示例](docs/使用示例.md) - 实际应用场景、最佳实践、故障恢复
* [⚙️ 环境变量配置指南](docs/环境变量配置指南.md) - 详细的环境变量配置说明和最佳实践



## 致谢

本项目基于[TrafeX/docker-php-nginx](https://github.com/TrafeX/docker-php-nginx) 进行优化和改进，感谢原作[@TrafeX](https://github.com/TrafeX) 提供的优秀基础项目
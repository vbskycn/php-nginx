# Docker PHP-FPM 8.4 & Nginx 1.26 基于 Alpine Linux

基于 [Alpine Linux](https://www.alpinelinux.org/) 构建的 Docker PHP-FPM 8.4 & Nginx 1.26 容器镜像示例。

仓库地址: https://github.com/vbskycn/php-nginx

## 特性

* 基于轻量级且安全的 Alpine Linux 发行版构建
* 多平台支持，支持 AMD64, ARMv6, ARMv7, ARM64
* 极小的 Docker 镜像大小（约40MB）
* 使用 PHP 8.4 以获得最佳性能、低CPU使用率和内存占用
* **环境变量配置系统** - 支持通过环境变量灵活配置，适应不同设备规格
* 针对512M VPS优化，支持50个并发用户，可扩展到更大规格服务器
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
* **并发控制**：最大50个PHP-FPM进程，支持50个并发用户
* **缓存策略**：启用OPcache加速，Redis LRU淘汰策略，静态资源5天缓存
* **轻量级基础**：基于Alpine Linux，镜像大小仅约40MB
* **资源监控**：所有服务日志统一输出，便于监控和调试

## 快速开始

### 基本使用

启动Docker容器：

```bash
docker run -p 80:8080 zhoujie218/php-nginx
```

访问以下地址：
- **首页信息**: http://localhost

### 挂载自定义代码

```bash
docker run -p 80:8080 -v ~/my-codebase:/var/www/html zhoujie218/php-nginx
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

#### PHP配置
| 变量名 | 默认值 | 说明 |
|--------|--------|------|
| `PHP_MEMORY_LIMIT` | 64M | PHP内存限制 |
| `OPCACHE_MEMORY_CONSUMPTION` | 32 | OPcache内存大小(MB) |
| `OPCACHE_INTERNED_STRINGS_BUFFER` | 4 | 内部字符串缓冲区(MB) |
| `OPCACHE_MAX_ACCELERATED_FILES` | 2000 | 最大加速文件数 |
| `OPCACHE_REVALIDATE_FREQ` | 60 | 重新验证频率(秒) |

#### PHP-FPM配置
| 变量名 | 默认值 | 说明 |
|--------|--------|------|
| `PHP_FPM_MAX_CHILDREN` | 50 | 最大子进程数 |
| `PHP_FPM_START_SERVERS` | 2 | 启动服务器数 |
| `PHP_FPM_MIN_SPARE_SERVERS` | 1 | 最小空闲服务器数 |
| `PHP_FPM_MAX_SPARE_SERVERS` | 10 | 最大空闲服务器数 |
| `PHP_FPM_PROCESS_IDLE_TIMEOUT` | 10s | 进程空闲超时时间 |
| `PHP_FPM_MAX_REQUESTS` | 1000 | 每个进程最大请求数 |

#### Redis配置
| 变量名 | 默认值 | 说明 |
|--------|--------|------|
| `REDIS_MAXMEMORY` | 64mb | Redis最大内存 |
| `REDIS_MAXMEMORY_POLICY` | allkeys-lru | 内存淘汰策略 |

#### Nginx配置
| 变量名 | 默认值 | 说明 |
|--------|--------|------|
| `NGINX_WORKER_PROCESSES` | auto | Nginx工作进程数 |
| `NGINX_WORKER_CONNECTIONS` | 1024 | 每个工作进程的连接数 |

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
      - PHP_FPM_MAX_CHILDREN=80
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
      - PHP_FPM_MAX_CHILDREN=120
      - REDIS_MAXMEMORY=256mb
      - NGINX_WORKER_PROCESSES=4
    restart: unless-stopped
```

**直接使用Docker命令：**
```bash
docker stop php-nginx
docker rm php-nginx

docker run -d \
  --name php-nginx \
  --restart=always \
  -p 80:8080 \
  -e PHP_MEMORY_LIMIT=64M \
  -e OPCACHE_MEMORY=128 \
  -e REDIS_MAXMEMORY=64mb \
  -e FPM_PM_MODE=static \
  -e FPM_MAX_CHILDREN=20 \
  -e NGINX_WORKER_PROCESSES=1 \
  -e NGINX_WORKER_CONNECTIONS=2048 \
  -e PHP_DISPLAY_ERRORS=On \
  -e PHP_MAX_EXECUTION_TIME=300 \
  -e OPCACHE_ENABLE=0 \
  zhoujie218/php-nginx:1.1.46
```

## 版本管理

主要或次要更改总是作为[发布版本](https://github.com/zhoujie218/php-nginx/releases)发布，并附有相应的变更日志。
`latest`标签每周自动更新，包含Alpine Linux的最新补丁。

## 配置

在[config/](config/)目录中，您可以找到Nginx、PHP和PHP-FPM的默认配置文件。
如果您想扩展或自定义配置，可以通过在正确的文件夹中挂载配置文件来实现：

Nginx配置：

    docker run -v "`pwd`/nginx-server.conf:/etc/nginx/conf.d/server.conf" zhoujie218/php-nginx

PHP配置：

    docker run -v "`pwd`/php-setting.ini:/etc/php84/conf.d/settings.ini" zhoujie218/php-nginx

PHP-FPM配置：

    docker run -v "`pwd`/php-fpm-settings.conf:/etc/php84/php-fpm.d/server.conf" zhoujie218/php-nginx

_注意：因为`-v`需要绝对路径，我在示例中添加了`pwd`来返回当前目录的绝对路径_



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
# Docker PHP-FPM 8.4 & Nginx 1.26 基于 Alpine Linux

基于 [Alpine Linux](https://www.alpinelinux.org/) 构建的 Docker PHP-FPM 8.4 & Nginx 1.26 容器镜像示例。

仓库地址: https://github.com/vbskycn/php-nginx

## 特性

* 基于轻量级且安全的 Alpine Linux 发行版构建
* 多平台支持，支持 AMD64, ARMv6, ARMv7, ARM64
* 极小的 Docker 镜像大小（约40MB）
* 使用 PHP 8.4 以获得最佳性能、低CPU使用率和内存占用
* 针对50个并发用户优化，限制并发处理PHP文件的请求数
* 优化为仅在流量时使用资源（通过使用PHP-FPM的`on-demand`进程管理器）
* Nginx、PHP-FPM和supervisord服务在非特权用户（nobody）下运行，更加安全
* 所有服务的日志都重定向到Docker容器的输出（可通过`docker logs -f <容器名称>`查看）
* 遵循KISS原则（Keep It Simple, Stupid），易于理解和调整镜像以满足您的需求

[![Docker Pulls](https://img.shields.io/docker/pulls/zhoujie218/php-nginx.svg)](https://hub.docker.com/r/zhoujie218/php-nginx/)
![nginx 1.26](https://img.shields.io/badge/nginx-1.26-brightgreen.svg)
![php 8.4](https://img.shields.io/badge/php-8.4-brightgreen.svg)
![License MIT](https://img.shields.io/badge/license-MIT-blue.svg)

## 项目目标

这个容器镜像的目标是提供一个在容器中运行Nginx和PHP-FPM的示例，支持多种VPS配置的自动优化，遵循最佳实践，易于理解和修改以满足您的需求。特别适合从512M到4GB内存的各种服务器环境。

## 多配置支持特性

* **智能配置**：支持5种VPS配置预设（1H512M、1H1G、1H2G、2H2G、2H4G）
* **动态优化**：根据硬件配置自动调整PHP、Redis、Nginx参数
* **内存优化**：针对不同内存大小优化PHP内存限制、OPcache、Redis配置
* **进程管理**：智能选择PHP-FPM进程管理模式（ondemand/dynamic/static）
* **并发控制**：根据CPU和内存配置优化最大进程数和并发处理能力
* **轻量级基础**：基于Alpine Linux，镜像大小仅约40MB
* **资源监控**：所有服务日志统一输出，便于监控和调试

## 快速开始

### 基本使用

启动Docker容器（使用默认1H512M配置）：

```bash
docker run -p 80:8080 zhoujie218/php-nginx
```

### 多配置支持

根据您的VPS配置选择合适的预设：

```bash
# 1H512M配置（默认，适合小型网站）
docker run -p 80:8080 -e VPS_CONFIG=1H512M zhoujie218/php-nginx

# 1H1G配置（适合中型网站）
docker run -p 80:8080 -e VPS_CONFIG=1H1G zhoujie218/php-nginx

# 1H2G配置（适合大型网站）
docker run -p 80:8080 -e VPS_CONFIG=1H2G zhoujie218/php-nginx

# 2H2G配置（适合高并发应用）
docker run -p 80:8080 -e VPS_CONFIG=2H2G zhoujie218/php-nginx

# 2H4G配置（适合企业级应用）
docker run -p 80:8080 -e VPS_CONFIG=2H4G zhoujie218/php-nginx
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
      - VPS_CONFIG=1H1G  # 根据您的VPS配置选择
    restart: unless-stopped
```

启动服务：

```bash
docker-compose up -d
```

### 环境变量配置

| 变量名 | 默认值 | 说明 |
|--------|--------|------|
| `VPS_CONFIG` | 1H512M | VPS配置预设（1H512M/1H1G/1H2G/2H2G/2H4G） |
| `PHP_MEMORY_LIMIT` | 64M | PHP内存限制 |
| `REDIS_MAXMEMORY` | 64mb | Redis最大内存 |
| `PHP_FPM_MAX_CHILDREN` | 20 | PHP-FPM最大进程数 |

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
* [⚙️ 配置指南](docs/配置指南.md) - VPS配置预设、环境变量、性能优化
* [🚀 部署指南](docs/部署指南.md) - 工作流触发、版本管理、镜像使用
* [📖 项目指南](docs/项目指南.md) - 贡献指南、开发指南、代码规范
* [💡 使用示例](docs/使用示例.md) - 实际应用场景、最佳实践、故障恢复



## 致谢

本项目基于[TrafeX/docker-php-nginx](https://github.com/TrafeX/docker-php-nginx) 进行优化和改进，感谢原作[@TrafeX](https://github.com/TrafeX) 提供的优秀基础项目
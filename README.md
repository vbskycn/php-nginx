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

这个容器镜像的目标是提供一个在容器中运行Nginx和PHP-FPM的示例，专门针对512M VPS进行优化，遵循最佳实践，易于理解和修改以满足您的需求。特别适合资源受限的小型服务器环境。如你需要可以自行调整并重新编译

## 512M VPS 优化特性

* **内存优化**：PHP内存限制64MB，OPcache内存32MB，Redis内存限制64MB
* **进程管理**：PHP-FPM使用`on-demand`模式，按需创建进程，空闲时自动回收
* **并发控制**：最大50个PHP-FPM进程，支持50个并发用户
* **缓存策略**：启用OPcache加速，Redis LRU淘汰策略，静态资源5天缓存
* **轻量级基础**：基于Alpine Linux，镜像大小仅约40MB
* **资源监控**：所有服务日志统一输出，便于监控和调试

## 使用方法

启动Docker容器：

    docker run -p 80:8080 zhoujie218/php-nginx

在 http://localhost 查看PHP信息，或在 http://localhost/test.html 查看静态HTML页面

或者挂载您自己的代码由PHP-FPM & Nginx提供服务：

    docker run -p 80:8080 -v ~/my-codebase:/var/www/html zhoujie218/php-nginx

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

## 文档和示例

要修改此容器以满足您的特定需求，请查看以下示例：

* [添加xdebug支持](https://github.com/zhoujie218/php-nginx/blob/main/docs/xdebug-support.md)
* [添加composer](https://github.com/zhoujie218/php-nginx/blob/main/docs/composer-support.md)
* [获取负载均衡器后客户端的真实IP](https://github.com/zhoujie218/php-nginx/blob/main/docs/real-ip-behind-loadbalancer.md)
* [发送邮件](https://github.com/zhoujie218/php-nginx/blob/main/docs/sending-emails.md)
* [启用HTTPS](https://github.com/zhoujie218/php-nginx/blob/main/docs/enable-https.md)



## 致谢

本项目基于[TrafeX/docker-php-nginx](https://github.com/TrafeX/docker-php-nginx) 进行优化和改进，感谢原作[@TrafeX](https://github.com/TrafeX) 提供的优秀基础项目
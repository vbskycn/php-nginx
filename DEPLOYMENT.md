# 部署说明

## 工作流触发方式

### 1. 推送到 main 分支
```bash
git push origin main
```
**效果**：
- ✅ 运行测试
- ✅ 构建并推送 Docker 镜像（latest 标签）
- ❌ 不创建 GitHub Release

### 2. 创建版本标签
```bash
# 创建标签
git tag v1.0.0
git push origin v1.0.0

# 或者不使用 v 前缀
git tag 1.0.0
git push origin 1.0.0
```
**效果**：
- ✅ 运行测试
- ✅ 构建并推送 Docker 镜像（版本标签）
- ✅ 自动创建 GitHub Release

### 3. 手动触发
在 GitHub Actions 页面手动运行工作流，可选择：
- 推送到 Docker Hub
- 指定分支

## 镜像标签

- `zhoujie218/php-nginx:latest` - 最新版本
- `zhoujie218/php-nginx:v1.0.0` - 版本标签（带 v 前缀）
- `zhoujie218/php-nginx:1.0.0` - 版本标签（不带 v 前缀）

## 使用镜像

```bash
# 拉取最新版本
docker pull zhoujie218/php-nginx:latest

# 拉取特定版本
docker pull zhoujie218/php-nginx:v1.0.0

# 运行容器
docker run -p 80:8080 zhoujie218/php-nginx:latest
```

## 注意事项

- Pull Request 不会触发 Docker 构建和 Release 创建
- 只有推送到 main 分支或创建版本标签才会执行发布操作
- 版本标签支持 `v1.0.0` 和 `1.0.0` 两种格式

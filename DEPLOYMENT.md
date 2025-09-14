# 部署说明

## 工作流触发方式

### 1. 更新版本文件并推送到 main 分支
```bash
# 编辑 version 文件
echo "php-nginx:1.0.6" > version

# 提交并推送
git add version
git commit -m "Update version to 1.0.6"
git push origin main
```
**效果**：
- ✅ 运行测试
- ✅ 构建并推送 Docker 镜像（latest 和版本标签）
- ✅ 自动创建 GitHub Release

### 2. 手动触发
在 GitHub Actions 页面手动运行工作流，可选择：
- 推送到 Docker Hub
- 指定分支

## 版本文件格式

`version` 文件格式：
```
php-nginx:1.0.6
```

- **名称**: `php-nginx`
- **版本号**: `1.0.6`
- **分隔符**: 使用冒号 `:` 分隔名称和版本

## 镜像标签

- `zhoujie218/php-nginx:latest` - 最新版本
- `zhoujie218/php-nginx:1.0.6` - 版本标签（从version文件读取）

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

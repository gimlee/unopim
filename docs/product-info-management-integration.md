# 商品信息管理系统接口配置

UnoPIM 与 `product-info-management` 之间只使用 HTTP API。商品、类目、属性、汇率通过 OAuth REST API 访问；商品图片和视频通过受保护的 `GET /api/v1/rest/media-files/download` 下载，不共享数据库或磁盘目录。

## 配置

UnoPIM `.env` 中维护：

- `PRODUCT_INFO_MANAGEMENT_BASE_URL`：商品信息管理系统地址。
- `PRODUCT_INFO_MANAGEMENT_API_TOKEN`：UnoPIM 后续调用对方接口时使用的服务令牌。
- `PRODUCT_INFO_MANAGEMENT_TIMEOUT`：接口超时秒数。
- `PIM_UNOPIM_*`：商品信息管理系统访问 UnoPIM REST API 的 OAuth 客户端与 API 用户。

两边的 `PIM_UNOPIM_*` / `UNOPIM_*` 值必须一致。首次部署或凭据调整后执行：

```powershell
php artisan unopim:passport:keys
php artisan unopim:integration:pim-provision
```

命令幂等更新专用 API 用户、OAuth 客户端和 API Key，不影响管理员账号。

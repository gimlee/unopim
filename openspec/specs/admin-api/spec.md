# 开放接口与系统集成规格 (Admin API Specification)

## Purpose

开放接口核心能力为外部系统、上下游服务及流水线组件提供符合标准规范的 RESTful API，涵盖 OAuth2 (Passport) 与 API Key 鉴权、标准目录实体 CRUD、受保护二进制流媒体资产下载以及开箱即用的系统接口初始化指令。

## Requirements

### Requirement: OAuth2 与 API Key 统一鉴权体系
系统 SHALL 支持基于 Laravel Passport 的 OAuth2 协议（密码凭证模式与客户端凭证模式）及个人 API Key 对 REST 接口请求进行安全鉴权。

#### Scenario: 客户端凭证模式获取 Access Token
- **当 (WHEN)** 外部系统向 `/oauth/token` 端点提交合法的 `client_id` 与 `client_secret` 时
- **则 (THEN)** 系统 SHALL 签发包含约定 Scope 作用域的 Bearer Access Token，用于后续接口调用鉴权

#### Scenario: 个人 API Key 请求鉴权
- **当 (WHEN)** 外部微服务在 HTTP 请求头中携带合法的 `Authorization: Bearer {api_key}` 时
- **则 (THEN)** 系统 SHALL 校验 `api_keys` 表记录，匹配关联的角色权限并放行接口调用

### Requirement: 标准化商品目录 RESTful 端点
系统 SHALL 针对商品、类目、属性、属性族、渠道、语言及货币等核心实体提供支持分页、条件过滤与关联预加载的 REST 接口。

#### Scenario: 分页检索商品列表
- **当 (WHEN)** 授权客户端附带筛选参数请求 `GET /api/v1/rest/products` 时
- **则 (THEN)** 系统 SHALL 返回符合规范的分页商品列表，包含解析后的多语言属性值及相关关联关系数据

### Requirement: 受鉴权保护的流式媒体文件下载
系统 SHALL 提供受保护的二进制流传输端点用于下载商品图片与多媒体文件，避免直接向外暴露底层存储物理路径。

#### Scenario: 安全流式下载商品图片
- **当 (WHEN)** 具备权限的客户端请求 `GET /api/v1/rest/media-files/download` 并指定相对路径时
- **则 (THEN)** 系统 SHALL 以二进制流式响应传输文件，附带正确的 `Content-Type` 与 HTTP 缓存响应头，并在文件不存在时返回 `404`

### Requirement: 幂等式系统接口集成初始化
系统 SHALL 提供专用 Artisan 命令，自动化且幂等地初始化外部集成系统所需的 API 用户、OAuth 客户端与 API Key，避免干扰常规管理员账户。

#### Scenario: 执行集成准备初始化指令
- **当 (WHEN)** 在命令行执行 `php artisan unopim:integration:pim-provision` 时
- **则 (THEN)** 系统 SHALL 读取 `.env` 配置，幂等地创建或更新专用的集成管理员、OAuth 客户端及 API Key

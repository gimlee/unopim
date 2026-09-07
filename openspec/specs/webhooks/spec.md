# 事件驱动网络钩子规格 (Webhooks Specification)

## Purpose

事件驱动网络钩子（Webhooks）核心能力在商品目录实体发生创建、更新、删除等生命周期事件时，向外部订阅端点异步分发 HTTP 通知，通过独立的后台队列保障主业务低延迟，并维护详尽的投递调用与重试日志。

## Requirements

### Requirement: Webhook 订阅与事件过滤管理
系统 SHALL 允许管理员在后台配置 Webhook 订阅，设置回调目标 URL、签名共享密钥（Secret），并勾选所关注的目录生命周期事件。

#### Scenario: 注册商品变更事件订阅
- **当 (WHEN)** 管理员添加订阅 `catalog.product.update.after` 事件的 Webhook，指定目标地址与签名密钥时
- **则 (THEN)** 系统 SHALL 将该订阅保存在 `webhooks` 表中，并将状态置为已激活

### Requirement: 独立后台队列异步投递
系统 SHALL 将外发 HTTP 回调派发至独立的 `webhooks` 队列异步执行，防止外部网络波动阻塞前端保存或导入进程。

#### Scenario: 异步调度商品更新事件推送
- **当 (WHEN)** 目录中的商品被编辑更新时
- **则 (THEN)** 系统 SHALL 捕获变更增量，并将 `SendProductWebhook` 任务投递至 `webhooks` 专用队列

#### Scenario: 队列 Worker 执行实际 HTTP 投递
- **当 (WHEN)** 后台工作进程消费处理 Webhook 任务时
- **则 (THEN)** 系统 SHALL 向订阅端点发送携带签名摘要及时间戳防篡改头信息的 HTTP POST 请求

### Requirement: 投递历史审计与异常重试追踪
系统 SHALL 详尽记录每一次 Webhook 调用的请求头、请求体载荷、响应状态码、执行耗时与错误信息。

#### Scenario: 记录成功的 HTTP 投递日志
- **当 (WHEN)** Webhook 接收方成功响应 HTTP 状态码 `200` 时
- **则 (THEN)** 系统 SHALL 在 `webhook_logs` 中记录本次调用的成功状态及耗时细节

#### Scenario: 记录投递失败并触发重试机制
- **当 (WHEN)** 外部订阅端点出现网络超时或返回 `500` 错误时
- **则 (THEN)** 系统 SHALL 记录详细失败异常并在满足重试策略时安排后续重试执行

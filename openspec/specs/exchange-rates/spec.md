# 汇率管理与自动化同步规格 (Exchange Rates Specification)

## Purpose

汇率管理核心能力以人民币（CNY）为基准锚点，通过 Laravel 调度器每三小时自动从 Frankfurter API 抓取实时外汇牌价，具备多级历史容灾回退能力，支持运营人员维护商业销售汇率，并通过管理界面和 REST API 暴露交叉汇率换算矩阵。

## Requirements

### Requirement: 自动化多币种汇率抓取与容灾
系统 SHALL 每隔 3 小时按调度计划并支持通过手动指令，自动同步目标货币（`USD`、`MYR`、`THB`）相对基准货币 `CNY` 的外汇汇率。

#### Scenario: 成功抓取 Frankfurter 最新汇率
- **当 (WHEN)** 调度器或带有 `--force` 参数的 `unopim:exchange-rates:refresh` 执行且第三方 API 正常响应时
- **则 (THEN)** 系统 SHALL 解析并计算 `1 CNY = N 目标币种` 的汇率比值，将结果存入 `exchange_rates` 表并打上 `fetched_at` 抓取时间戳

#### Scenario: 接口故障时的三级容灾回退
- **当 (WHEN)** 当前外部 API 请求失败或返回数据不完整时
- **则 (THEN)** 系统 SHALL 尝试拉取前一天的历史数据并计算日均值；若仍不可达，则回退至本地 31 天内存储的最近一条成功记录

### Requirement: 双汇率机制（真实牌价 vs. 销售汇率）
系统 SHALL 分离维护外部真实汇率与商业销售汇率，并在商品数据导入计价与多币种价格计算中以销售汇率为换算基准。

#### Scenario: 运营调整销售结算汇率
- **当 (WHEN)** 管理员在后台 `/admin/settings/exchange-rates` 页面手动调整某币种的销售汇率时
- **则 (THEN)** 系统 SHALL 更新 `exchange_rate_selling` 并在后续的商品价格计算与导出中即时生效该销售汇率

#### Scenario: 商品导入时自动折算外币价格
- **当 (WHEN)** 商品导入仅提供了基础 CNY 价格时
- **则 (THEN)** 系统 SHALL 基于当前有效的销售汇率自动换算并补充保存 USD、MYR 及 THB 对应的价格属性

### Requirement: 汇率开放 REST 接口
系统 SHALL 提供受鉴权保护的 REST API 端点，输出基准货币、实时汇率、销售汇率以及跨币种直接折算矩阵。

#### Scenario: 查询汇率矩阵接口
- **当 (WHEN)** 授权客户端请求 `GET /api/v1/rest/exchange-rates` 时
- **则 (THEN)** 系统 SHALL 返回包含 CNY 基准币种、最新真实汇率、当前销售汇率及全币种相互折算矩阵的 JSON 数据

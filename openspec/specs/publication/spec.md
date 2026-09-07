# 外部发布渠道与版本快照规格 (Publication Specification)

## Purpose

外部发布渠道核心能力统一管理各销售渠道的数据分发，生成不可变（Immutable）的版本化商品载荷快照，执行发布、撤回、修订和恢复的状态生命周期流转，并通过专用发布队列异步派发数据。

## Requirements

### Requirement: 不可变版本化快照发布
系统 SHALL 在将目录商品发布至各外部渠道时，生成版本递增的快照记录，且一经发布即禁止就地覆写篡改。

#### Scenario: 发布新版本商品快照
- **当 (WHEN)** 目录商品成功发布至已启用的发布渠道时
- **则 (THEN)** 系统 SHALL 在 `publication_versions` 中新增自增版本记录，将生成的 JSON 载荷存储于 `publication_version_payloads`，并封锁该版本的后续就地修改

#### Scenario: 拒绝覆写已发布版本
- **当 (WHEN)** 任何直接修改既有已发布版本记录的请求到达时
- **则 (THEN)** 系统 SHALL 抛出 `ImmutableVersionException` 异常并拒绝变更操作

### Requirement: 发布生命周期状态流转与审计
系统 SHALL 支持完备的发布状态机（草稿、已发布、已撤回、已修订），受严格权限控制并记录全链路审计流。

#### Scenario: 撤回在线发布版本
- **当 (WHEN)** 拥有权限的管理员请求下线某个生效中的发布版本时
- **则 (THEN)** 系统 SHALL 将状态流转为 `withdrawn`，在审计日志中记录操作者与时间，并分发 `PublicationWithdrawn` 事件

#### Scenario: 修订涉及敏感内容的发布版本
- **当 (WHEN)** 发布版本因法规或隐私原因需要紧急遮蔽敏感数据时
- **则 (THEN)** 系统 SHALL 将状态标记为 `redacted`，擦除指定敏感载荷字段，并持久化记录修订原因

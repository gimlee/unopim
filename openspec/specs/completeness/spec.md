# 商品信息完整度评分规格 (Product Completeness Specification)

## Purpose

商品信息完整度核心能力按属性族与销售渠道维度配置必填属性规则，并在后台异步队列中评估商品在各个渠道和语言维度的填充完成率，向运营人员呈现质量评分与缺失属性诊断。

## Requirements

### Requirement: 完整度必填属性规则配置
系统 SHALL 允许管理员按“属性族 + 销售渠道”的颗粒度，灵活定义参与完整度考核的必填属性清单。

#### Scenario: 配置特定渠道下的属性族必填项
- **当 (WHEN)** 管理员为某个属性族在指定销售渠道勾选一批关键属性作为必填项时
- **则 (THEN)** 系统 SHALL 在 `completeness_settings` 表中建立属性族、渠道与必填属性之间的关联配置

#### Scenario: 变体层级属性所有权过滤
- **当 (WHEN)** 为子变体商品评估必填规则时
- **则 (THEN)** 系统 SHALL 借助 `VariantStructurePlanner` 排除仅属于父级可配置商品持有的属性，确保子变体仅考核自身可编辑的属性

### Requirement: 异步队列驱动的完整度计算
系统 SHALL 在商品发生实际内容变更或执行批量重算时，将计分任务派发至专用的 `completeness` 队列异步执行。

#### Scenario: 商品内容更新触发异步评分任务
- **当 (WHEN)** 商品属性被修改且模型标记 `wasDirtyOnUpdate = true` 时
- **则 (THEN)** 系统 SHALL 向 `completeness` 队列分发 `ProductCompletenessJob` 异步执行完整度计分计算

#### Scenario: 过滤无变动保存以节省计算资源
- **当 (WHEN)** 商品执行保存但属性未发生任何实际变化（`wasDirtyOnUpdate = false`）时
- **则 (THEN)** 系统 SHALL 跳过分发完整度任务，避免无效占用后台队列资源

### Requirement: 多语言多渠道评分与缺失项诊断
系统 SHALL 计算并持久化商品在每个销售渠道及对应启用的各门语言下的百分比得分与缺失字段数量。

#### Scenario: 记录分渠道分语言的完整度得分
- **当 (WHEN)** 完整度计算作业完成评估时
- **则 (THEN)** 系统 SHALL 在 `product_completeness` 表中保存对应 `locale_id` 和 `channel_id` 的得分（0~100%）及 `missing_count` 缺失字段数

#### Scenario: 在商品列表直观展示完整度状态
- **当 (WHEN)** 商品表格在当前选定渠道下渲染商品数据时
- **则 (THEN)** 系统 SHALL 显示对应百分比徽标，并支持浮窗悬停提示具体的缺失属性名称

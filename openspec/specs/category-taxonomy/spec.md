# 类目分类与平台映射规格 (Category Taxonomy Specification)

## Purpose

类目分类与平台映射核心能力基于嵌套集合模型管理树状标准类目，支持动态类目扩展字段、外部电商平台类目树（如 TikTok Shop）、上游采购渠道映射（如 1688），并提供融合规则启发与受限大模型判定的双模商品分类引擎。

## Requirements

### Requirement: 树状标准类目体系管理
系统 SHALL 采用嵌套集合（Nested Set）数据结构维护 PIM 标准类目树，支持子树的高性能遍历与单次查询。

#### Scenario: 创建子类目并维护边界值
- **当 (WHEN)** 管理员指定 `parent_id` 创建新类目时
- **则 (THEN)** 系统 SHALL 在 `categories` 表中新增记录，并自动重新计算树节点的 `_lft` 与 `_rgt` 边界以维持树形完整性

#### Scenario: 子树范围商品查询
- **当 (WHEN)** 目录查询请求检索某个父级类目分支下的全部商品时
- **则 (THEN)** 系统 SHALL 基于 `_lft` 与 `_rgt` 边界条件在单次数据库查询中高效命中所有后代类目

### Requirement: 平台类目树与标准映射关系
系统 SHALL 存储外部平台（如 TikTok Shop）的官方类目树，并维护 PIM 标准类目与平台外部类目间的映射关系。

#### Scenario: 同步外部平台类目树
- **当 (WHEN)** 同步外部电商平台（例如 TikTok Shop 马来西亚站点）类目数据时
- **则 (THEN)** 系统 SHALL 将外部层级保存在 `platform_categories` 中，并记录外部官方 ID (`external_id`) 及叶子节点状态

#### Scenario: 配置标准类目到平台类目的映射
- **当 (WHEN)** 管理员为 PIM 标准类目建立到平台类目的映射、指定映射类型与优先级时
- **则 (THEN)** 系统 SHALL 将映射持久化在 `category_mappings` 中，用于商品跨平台发布与类目审核流程

### Requirement: 上游采购渠道来源类目映射
系统 SHALL 支持将上游供应商平台（如 1688）的来源类目自动映射至 PIM 标准类目，以加速商品采纳与建档。

#### Scenario: 商品导入时自动识别 1688 来源类目
- **当 (WHEN)** 导入带有 1688 来源类目标识的商品且该标识命中 `category_source_mappings` 中的有效记录时
- **则 (THEN)** 系统 SHALL 自动为该商品分配对应的 PIM 标准主类目

### Requirement: 双模商品类目分类引擎与隔离缓存
系统 SHALL 提供规则驱动与 AI 驱动两种分类方式，并在修改正式商品数据之前，将推荐结果隔离缓存在分类缓存表中。

#### Scenario: 规则驱动的确定性分类
- **当 (WHEN)** 管理员对商品触发“规则分类”时
- **则 (THEN)** 系统 SHALL 综合评估 1688 映射、类目多语言别名 (`category_aliases`) 及关键词规则 (`category_classification_rules`)，将最高置信度结果存入 `product_category_classification_caches`

#### Scenario: 受限候选集的 AI 智能分类
- **当 (WHEN)** 对商品触发“AI 分类”且指定了启用的 Magic AI 平台与模型时
- **则 (THEN)** 系统 SHALL 在本地生成标准类目与平台类目的有限候选集，强制要求大模型只能从候选列表中进行选择，并严格拒绝任何超出候选范围的模型输出

#### Scenario: 确认并应用分类建议
- **当 (WHEN)** 操作人员在审核界面点击“使用此结果”时
- **则 (THEN)** 系统 SHALL 原子更新商品主属性中的 PIM 标准类目，并在 `product_platform_category_assignments` 中记录确认的商品级平台类目

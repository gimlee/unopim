# 商品目录规格 (Catalog Product Specification)

## Purpose

商品目录核心能力负责集中管理单品（Simple）与可配置商品（Configurable）、多维变体坐标轴结构、基于四维 JSON 作用域的动态 EAV 属性值存储、商品多媒体画廊以及目录实体间的关联关系。

## Requirements

### Requirement: 商品生命周期与唯一标识
系统 SHALL 支持单品与可配置商品类型，在整个目录中强制执行 SKU 唯一性约束，并跟踪商品上下架状态。

#### Scenario: 创建单品商品
- **当 (WHEN)** 管理员创建类型为 `simple`、关联既有属性族 ID 且拥有唯一 SKU `TSHIRT-BLK-S` 的商品
- **则 (THEN)** 系统 SHALL 在 `products` 表中持久化商品记录，将状态置为 `true` 并初始化空或默认的属性值载荷

#### Scenario: 拒绝重复 SKU
- **当 (WHEN)** 管理员或 API 客户端尝试创建已存在于 `products` 表中的 SKU 商品时
- **则 (THEN)** 系统 SHALL 拒绝保存操作并返回唯一性校验失败错误

### Requirement: 动态属性值存储与四维作用域
系统 SHALL 将商品动态属性值保存在结构化 JSON 字段中，并划分为 `common`（全局通用）、`locale_specific`（语言特定）、`channel_specific`（渠道特定）和 `channel_locale_specific`（渠道与语言复合特定）四维作用域。

#### Scenario: 存储多作用域商品属性值
- **当 (WHEN)** 提交的商品属性值包含 `common` 作用域下的品牌代码、`locale_specific.zh_CN.name` 下的中文标题以及 `channel_locale_specific.ecommerce.zh_CN.description` 下的独立站中文描述时
- **则 (THEN)** 系统 SHALL 将其序列化并分别持久化在 `values` JSON 列的对应键路径下

#### Scenario: 商品展示名称的兜底解析
- **当 (WHEN)** 读取某个未配置特定渠道与语言名称的商品展示名时
- **则 (THEN)** 系统 SHALL 依次回退到标准语言的 `locale_specific`、`common.name`，若仍为空则最终回退至商品的 `sku`

### Requirement: 可配置商品与多层变体继承
系统 SHALL 支持由 `parent_id` 关联、依变体结构轴（Variant Structure Axes）展开的可配置父商品与子变体层级结构。

#### Scenario: 运行时动态解析变体继承属性值
- **当 (WHEN)** 通过 `resolvedValues()` 解析子变体商品的属性值载荷时
- **则 (THEN)** 系统 SHALL 将子变体自身的属性值叠加在父商品的属性值之上，保留父级定义的公共属性，同时生效子变体的覆盖属性

#### Scenario: 商品目录列表默认聚合展示
- **当 (WHEN)** 用户浏览后台商品主列表表格时
- **则 (THEN)** 系统 SHALL 默认仅展示可配置父商品与独立单品，排除底层子变体对主列表的视觉干扰

### Requirement: 商品关联关系
系统 SHALL 支持通过预定义的关联类型（如交叉销售、向上销售、相关配件）建立商品与商品之间的多向或单向关联。

#### Scenario: 建立相关商品关联
- **当 (WHEN)** 管理员在指定关联类型下为源商品关联一批目标商品 ID 时
- **则 (THEN)** 系统 SHALL 将关系持久化至 `product_associations` 表，并在商品详情页与 API 响应中暴露关联商品数据

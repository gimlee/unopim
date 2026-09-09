# 商品目录规格 (Catalog Product Specification)

## Purpose

商品目录核心能力负责集中管理单品（Simple）与可配置商品（Configurable）、多维变体坐标轴结构、基于四维 JSON 作用域的动态 EAV 属性值存储、商品多媒体画廊以及目录实体间的关联关系。同时提供现代化跨境电商商品工作台体系，涵盖一键式多阶段 AI 优化流水线、多地区并发上架调度与实时停止、TikTok Shop 上架自动化与异步状态轮询、上架履历全景抽屉、上架异常生命周期治理、商品多语言图片翻译与可视化对比。

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

### Requirement: 一键式多阶段 AI 优化流水线
系统 SHALL 支持商品分类、商品名称提炼与商品描述优化三阶段的自动化串联执行，支持阶段选择（`stage=all|category|name|description`），并自动识别各阶段的历史优化状态以避免重复执行。

#### Scenario: 一键触发全链路智能优化
- **当 (WHEN)** 运营人员在商品编辑页或列表行点击“AI 优化”且指定 `stage=all` 时
- **则 (THEN)** 系统 SHALL 依次执行：检查并补全商品标准与平台类目、依据名称模板与负向词过滤优化商品标题、依据描述模板与质量规则优化富文本描述，并在前端通知条幅中分项反馈各阶段执行结果（如已优化则自动跳过）

#### Scenario: 针对特定阶段的定向优化
- **当 (WHEN)** 用户仅针对单个维度（例如仅重新优化商品描述 `stage=description`）发起请求时
- **则 (THEN)** 系统 SHALL 仅调度对应阶段的 AI 处理逻辑，并将生成的优化内容与修订记录落库，不触动其余维度的既有内容

### Requirement: TikTok Shop 多地区并发上架调度与实时控制
系统 SHALL 支持向外部 PIM 自动化服务派发多地区（如马来西亚 MY、泰国 TH、新加坡 SG、菲律宾 PH、越南 VN 等）的商品上架任务，区分草稿保存与提交审核动作，并支持实时查询状态与紧急停止上架。

#### Scenario: 发起商品上架草稿或提审
- **当 (WHEN)** 管理员选择目标国家/地区并点击“上架草稿”或“提交审核”时
- **则 (THEN)** 系统 SHALL 向 PIM 执行端点发起请求，启动上架自动化流水线，并返回处于 `processing` 处理中状态的响应

#### Scenario: 实时取消进行中的上架任务
- **当 (WHEN)** 操作人员在任务执行中点击“停止上架”时
- **则 (THEN)** 系统 SHALL 向自动化服务派发 `/listing/cancel` 指令，终止当前正在执行的浏览器调度与上架会话，并将状态置为已停止

#### Scenario: 异步轮询获取最新上架自动化状态
- **当 (WHEN)** 前端工作台以指定地区参数轮询 `listingStatus` 接口时
- **则 (THEN)** 系统 SHALL 从底层自动化端点获取该商品的最新执行阶段、发布 URL、店铺后台链接或异常错误信息

### Requirement: 商品上架履历追踪与主列表状态展示
系统 SHALL 在 `product_listing_histories` 中持久化记录商品每一次上架尝试的执行过程与结果，并在商品编辑页以抽屉面板呈现完整履历，在主商品网格以状态徽标直观展示最新结果。

#### Scenario: 持久化记录上架执行历史
- **当 (WHEN)** 外部自动化上架端点完成一次上架尝试并回传执行报告时
- **则 (THEN)** 系统 SHALL 在 `product_listing_histories` 中插入包含 `attempt_id`、`region`、`listing_type`（草稿/提审）、`status`、`draft_url`、`seller_url` 及起止时间戳的记录

#### Scenario: 编辑页抽屉与主网格状态徽标展示
- **当 (WHEN)** 用户在商品编辑页展开“上架历史”抽屉或在商品主列表中浏览该商品时
- **则 (THEN)** 系统 SHALL 抽屉按时间倒序渲染最近 100 次的执行记录详情，并在商品主列表表格对应列展示该商品的最新上架状态徽标（草稿已保存、审核中、已发布、上架失败等）

### Requirement: 商品上架异常拦截与闭环处置
系统 SHALL 统一捕获商品上架全生命周期中抛出的阻塞性与非阻塞性异常，存储于 `product_listing_exceptions`，支持实时告警并提供人工标记解决的处置闭环。

#### Scenario: 捕获并持久化记录上架异常事件
- **当 (WHEN)** 上架过程中触发类目属性不全、资质缺失、价格超出区间或网络超时等异常时
- **则 (THEN)** 系统 SHALL 记录包含 `attempt_id`、`event_key`、`exception_type`、`stage`、`severity`、`blocking` 以及 JSON 详情的异常数据

#### Scenario: 运营人员标记异常解决状态
- **当 (WHEN)** 运营人员修正商品数据后在异常面板将指定异常标记为 `resolved = true` 时
- **则 (THEN)** 系统 SHALL 更新该异常记录的 `resolved_at` 时间戳，解除阻塞标记；同时支持在重新发现问题时重新打开异常

### Requirement: 商品图片多语言翻译与可视化查看器
系统 SHALL 支持提取商品主图、画廊图及详情图中的原始文本，调用多语言翻译模型将其翻译为目标语种并合成新图，保存在 `product_image_translations`，并在后台提供原图/译图左右对比面板及双击大图无损放大查看器。

#### Scenario: 针对指定国家/地区执行图片翻译
- **当 (WHEN)** 运营人员在商品图片翻译模态框中勾选目标地区（例如泰国 TH-泰文、马来西亚 MY-马来文）并点击翻译时
- **则 (THEN)** 系统 SHALL 调度图片翻译服务，生成对应语言版本的译图，并持久化记录 `original_url`、`translated_url`、`region` 与 `locale`

#### Scenario: 双语对比查看与双击大图放大查看器
- **当 (WHEN)** 用户在图片翻译面板查看已生成的译图并双击任意图片时
- **则 (THEN)** 系统 SHALL 弹出高分辨率全屏图片查看器，支持多图左右并排联动对比与大图无损放大检查


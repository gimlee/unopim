<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $exists = DB::table('magic_ai_system_prompts')
            ->where('title', '商品名称优化（标准模板）')
            ->exists();

        if (! $exists) {
            DB::table('magic_ai_system_prompts')->insert([
                'title'       => '商品名称优化（标准模板）',
                'purpose'     => 'product_name',
                'tone'        => '你是专业的电商商品标题与名称优化专家。请根据输入的商品信息（原始名称、描述、规格属性等），提炼并生成极具吸引力、通顺且符合电商搜索与点击习惯的优质商品标题/名称。

必须严格遵守以下规则：
1. 【绝对禁止货源表述】：严禁提及“厂家”、“工厂”、“源头工厂”、“直销”、“厂家直销”、“一手货源”、“批发”、“代发”、“一件代发”、“代工”、“加工”、“定制”等任何形式的货源、供应或批发表述。
2. 【特性功能联想与深度拓展】：必须充分挖掘并联想商品的实用特性与扩张功能、适用场景、痛点解决、多用途搭配及核心优势，突出产品为消费者带来的实际价值与品质体验。
3. 【禁止价格与平台信息】：严禁出现任何价格、金额、币种、折扣、促销词（如爆款、清仓、秒杀等）；严禁提及任何电商平台名称（如淘宝、1688、拼多多、天猫、京东、亚马逊、Shopee、Lazada、TikTok等）或店铺、上架等内部信息。
4. 【真实客观与合规】：严禁虚构不存在的参数或功效，严禁使用国家广告法禁用的极限词（如最好、第一、唯一、顶级等）。
5. 【格式与长度要求】：仅输出单行商品名称文本，不得换行，不得使用引号包围，不得包含任何 Markdown 格式或解释说明；长度严格控制在 60 至 120 个字符以内。',
                'max_tokens'  => 300,
                'temperature' => 0.3,
                'is_enabled'  => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('magic_ai_system_prompts')
            ->where('title', '商品名称优化（标准模板）')
            ->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('magic_ai_system_prompts', function (Blueprint $table): void {
            $table->string('purpose', 64)->default('general')->after('title');
            $table->index(['purpose', 'is_enabled'], 'magic_ai_system_prompts_purpose_enabled_idx');
        });

        $now = now();

        DB::table('magic_ai_system_prompts')->insert([
            [
                'title'       => 'AI 商品分类',
                'purpose'     => 'category_classification',
                'tone'        => '你是电商商品类目审核助手。根据商品名称、描述、规格和来源类目判断商品的核心用途，只能从用户提供的候选列表中选择最匹配的叶子类目。不得创造、改写或猜测候选范围以外的类目 ID；信息不足时选择证据最充分的候选，并简要说明依据。',
                'max_tokens'  => 400,
                'temperature' => 0.1,
                'is_enabled'  => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'title'       => '商品描述优化（标准模板）',
                'purpose'     => 'product_description',
                'tone'        => '你是专业的电商商品文案编辑。只描述商品本身可验证的信息，包括功能、材质、规格、结构、兼容性和使用方式。不得出现价格、金额、币种、折扣、促销或采购数量；不得提及国家、城市、地区、产地、原产国、制造地点、发货地、供应商所在地或目标市场；不得提及任何电商平台、店铺、销售渠道、销售目的、上架、转售、批发、零售或跨境业务。使用自然段组织内容，段落之间空一行，每个句子必须使用完整标点；表达自然、通顺、人类可读，避免关键词堆砌。不得虚构参数、认证、功效或卖点，不得使用夸大和绝对化表述。保持输入内容的主要语言。',
                'max_tokens'  => 1200,
                'temperature' => 0.2,
                'is_enabled'  => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('magic_ai_system_prompts')
            ->whereIn('title', ['AI 商品分类', '商品描述优化（标准模板）'])
            ->delete();

        Schema::table('magic_ai_system_prompts', function (Blueprint $table): void {
            $table->dropIndex('magic_ai_system_prompts_purpose_enabled_idx');
            $table->dropColumn('purpose');
        });
    }
};

<?php

namespace app\admin\controller\store;

use app\admin\controller\AuthController;
use service\FormBuilder as Form;
use app\admin\model\store\StoreProductAttr;
use app\admin\model\store\StoreProductAttrResult;
use app\admin\model\store\StoreProductRelation;
use app\admin\model\system\SystemConfig;
use service\JsonService;
use service\JsonService as Json;
use think\Db;
use traits\CurdControllerTrait;
use service\UtilService as Util;
use service\UploadService as Upload;
use think\Request;
use app\admin\model\store\StoreCategory as CategoryModel;
use app\admin\model\store\StoreProduct as ProductModel;
use app\admin\model\store\StoreTrip as TripModel;
use app\admin\model\store\StoreMerchant as MerchantModel;
use think\Url;

use app\admin\model\system\SystemAttachment;


/**
 * 产品管理
 * Class StoreProduct
 * @package app\admin\controller\store
 */
class StoreProduct extends AuthController
{

    use CurdControllerTrait;

    protected $bindModel = ProductModel::class;

    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index()
    {

        $type = $this->request->param('type');
        //获取分类
        $this->assign('cate', CategoryModel::getTierList());
        //出售中产品
        $onsale =  ProductModel::where(['is_show' => 1, 'is_del' => 0])->count();
        //待上架产品
        $forsale =  ProductModel::where(['is_show' => 0, 'is_del' => 0])->count();
        //仓库中产品
        $warehouse =  ProductModel::where(['is_del' => 0])->count();
        //已经售馨产品
        $outofstock = ProductModel::getModelObject()->where(ProductModel::setData(4))->count();
        //警戒库存
        $policeforce = ProductModel::getModelObject()->where(ProductModel::setData(5))->count();
        //回收站
        $recycle =  ProductModel::where(['is_del' => 1])->count();

        $this->assign(compact('type', 'onsale', 'forsale', 'warehouse', 'outofstock', 'policeforce', 'recycle'));
        return $this->fetch();
    }
    /**
     * 异步查找产品
     *
     * @return json
     */
    public function product_ist()
    {
        $where = Util::getMore([
            ['page', 1],
            ['limit', 20],
            ['store_name', ''],
            ['cate_id', ''],
            ['excel', 0],
            ['order', ''],
            ['type', $this->request->param('type')]
        ]);
        return JsonService::successlayui(ProductModel::ProductList($where));
    }
    /**
     * 设置单个产品上架|下架
     *
     * @return json
     */
    public function set_show($is_show = '', $id = '')
    {
        ($is_show == '' || $id == '') && JsonService::fail('缺少参数');
        $res = ProductModel::where(['id' => $id])->update(['is_show' => (int) $is_show]);
        if ($res) {
            return JsonService::successful($is_show == 1 ? '上架成功' : '下架成功');
        } else {
            return JsonService::fail($is_show == 1 ? '上架失败' : '下架失败');
        }
    }
    /**
     * 快速编辑
     *
     * @return json
     */
    public function set_product($field = '', $id = '', $value = '')
    {
        $field == '' || $id == '' || $value == '' && JsonService::fail('缺少参数');
        if (ProductModel::where(['id' => $id])->update([$field => $value]))
            return JsonService::successful('保存成功');
        else
            return JsonService::fail('保存失败');
    }
    /**
     * 设置批量产品上架
     *
     * @return json
     */
    public function product_show()
    {
        $post = Util::postMore([
            ['ids', []]
        ]);
        if (empty($post['ids'])) {
            return JsonService::fail('请选择需要上架的产品');
        } else {
            $res = ProductModel::where('id', 'in', $post['ids'])->update(['is_show' => 1]);
            if ($res)
                return JsonService::successful('上架成功');
            else
                return JsonService::fail('上架失败');
        }
    }
    /**
     * 显示创建资源表单页.
     *
     * @return \think\Response
     */
    public function create()
    {
        //        $this->assign(['title'=>'添加产品','action'=>Url::build('save'),'rules'=>$this->rules()->getContent()]);
        //        return $this->fetch('public/common_form');
        $field = [
            Form::select('mer_id', '所属店铺')->setOptions(function () {
                $list = MerchantModel::where(['status' => '1'])->select();
                $menus = [];
                foreach ($list as $menu) {
                    $menus[] = ['value' => $menu['id'], 'label' => $menu['store_name']]; //,'disabled'=>$menu['pid']== 0];
                }
                return $menus;
            })->filterable(1)->multiple(0),
            Form::select('cate_id', '产品分类')->setOptions(function () {
                $list = CategoryModel::getTierList();
                $menus = [];
                foreach ($list as $menu) {
                    $menus[] = ['value' => $menu['id'], 'label' => $menu['html'] . $menu['cate_name']]; //,'disabled'=>$menu['pid']== 0];
                }
                return $menus;
            })->filterable(1)->multiple(1),
            Form::input('store_name', '产品名称')->col(Form::col(24)),
            Form::input('store_info', '产品简介')->type('textarea'),
            Form::input('keyword', '产品关键字')->placeholder('多个用英文状态下的逗号隔开'),
            Form::input('unit_name', '产品单位', '件'),
            Form::frameImageOne('image', '产品主图片(305*305px)', Url::build('admin/widget.images/index', array('fodder' => 'image')))->icon('image')->width('100%')->height('500px'),
            Form::frameImages('slider_image', '产品轮播图(640*640px)', Url::build('admin/widget.images/index', array('fodder' => 'slider_image')))->maxLength(5)->icon('images')->width('100%')->height('500px')->spin(0),
            Form::number('price', '产品售价')->min(0)->col(8),
            Form::number('ot_price', '产品市场价')->min(0)->col(8),
            Form::number('give_integral', '赠送积分')->min(0)->precision(0)->col(8),
            Form::number('postage', '邮费')->min(0)->col(Form::col(8)),
            Form::number('sales', '销量', 0)->min(0)->precision(0)->col(8),
            Form::number('ficti', '虚拟销量')->min(0)->precision(0)->col(8),
            Form::number('stock', '库存')->min(0)->precision(0)->col(8),
            Form::number('cost', '产品成本价')->min(0)->col(8),
            Form::number('sort', '排序')->col(8),
            Form::radio('is_show', '产品状态', 0)->options([['label' => '上架', 'value' => 1], ['label' => '下架', 'value' => 0]])->col(8),
            Form::radio('is_hot', '热卖单品', 0)->options([['label' => '是', 'value' => 1], ['label' => '否', 'value' => 0]])->col(8),
            Form::radio('is_benefit', '促销单品', 0)->options([['label' => '是', 'value' => 1], ['label' => '否', 'value' => 0]])->col(8),
            Form::radio('is_best', '精品推荐', 0)->options([['label' => '是', 'value' => 1], ['label' => '否', 'value' => 0]])->col(8),
            Form::radio('is_new', '首发新品', 0)->options([['label' => '是', 'value' => 1], ['label' => '否', 'value' => 0]])->col(8),
            Form::radio('is_postage', '是否包邮', 0)->options([['label' => '是', 'value' => 1], ['label' => '否', 'value' => 0]])->col(8)
        ];
        $form = Form::make_post_form('添加产品', $field, Url::build('save'), 2);
        $this->assign(compact('form'));
        return $this->fetch('public/form-builder');
    }

    public function create_item()
    {
        $field = [
            Form::select('mer_id', '所属店铺')->setOptions(function () {
                $list = MerchantModel::where(['status' => '1'])->select();
                $menus = [];
                foreach ($list as $menu) {
                    $menus[] = ['value' => $menu['id'], 'label' => $menu['store_name']]; //,'disabled'=>$menu['pid']== 0];
                }
                return $menus;
            })->filterable(1)->multiple(0),
            // Form::select('cate_id','产品分类')->setOptions(function(){
            //     $list = CategoryModel::getTierList();
            //     $menus=[];
            //     foreach ($list as $menu){
            //         $menus[] = ['value'=>$menu['id'],'label'=>$menu['html'].$menu['cate_name']];//,'disabled'=>$menu['pid']== 0];
            //     }
            //     return $menus;
            // })->filterable(1)->multiple(1),
            Form::input('store_name', '产品名称')->col(Form::col(24)),
            Form::input('store_info', '产品简介')->type('textarea'),
            Form::input('keyword', '产品关键字')->placeholder('多个用英文状态下的逗号隔开'),
            Form::input('unit_name', '产品单位', '件'),
            Form::frameImageOne('image', '产品主图片(305*305px)', Url::build('admin/widget.images/index', array('fodder' => 'image')))->icon('image')->width('100%')->height('500px'),
            Form::frameImages('slider_image', '产品轮播图(640*640px)', Url::build('admin/widget.images/index', array('fodder' => 'slider_image')))->maxLength(5)->icon('images')->width('100%')->height('500px')->spin(0),

//            Form::input('process','行程安排')->col(24)->placeholder('输入行程和时间格式：事件,时间点（多个事件以|隔开）：例如：早晨海边散步,7:00|坐大巴车前往西湖,8:00'),

            // Form::dateRange('open_date', '项目开业日期')->type('daterange')->confirm(true)->showWeekNumbers(true),
            Form::city('open_address', '游玩项目地点')->type('city_area')->trigger('click')->filterable(true),
//            Form::input('attention', '费用须知')->placeholder('多个注意点用“ 。”做结尾,例如：1、车费自理。2、早餐自带。'),

            Form::number('view', '景点数')->min(0)->col(8),
            Form::number('eat', '餐饮次数')->min(0)->col(8),
            Form::number('item', '游玩项目数')->min(0)->col(8),

            Form::number('price', '产品售价')->min(0)->col(8),
            Form::number('ot_price', '产品市场价')->min(0)->col(8),
            Form::number('give_integral', '赠送积分')->min(0)->precision(0)->col(8),
            Form::number('postage', '邮费')->min(0)->col(Form::col(8)),
            Form::number('sales', '销量', 0)->min(0)->precision(0)->col(8),
            Form::number('ficti', '虚拟销量')->min(0)->precision(0)->col(8),
            Form::number('stock', '库存')->min(0)->precision(0)->col(8),
            Form::number('cost', '产品成本价')->min(0)->col(8),
            Form::number('sort', '排序')->col(8),
            Form::radio('is_show', '产品状态', 0)->options([['label' => '上架', 'value' => 1], ['label' => '下架', 'value' => 0]])->col(8),
            Form::radio('is_hot', '热卖单品', 0)->options([['label' => '是', 'value' => 1], ['label' => '否', 'value' => 0]])->col(8),
            Form::radio('is_benefit', '促销单品', 0)->options([['label' => '是', 'value' => 1], ['label' => '否', 'value' => 0]])->col(8),
            Form::radio('is_best', '精品推荐', 0)->options([['label' => '是', 'value' => 1], ['label' => '否', 'value' => 0]])->col(8),
            Form::radio('is_new', '首发新品', 0)->options([['label' => '是', 'value' => 1], ['label' => '否', 'value' => 0]])->col(8),
        ];
        $form = Form::make_post_form('添加游玩项目', $field, Url::build('save_item'), 2);
        $this->assign(compact('form'));
        return $this->fetch('public/form-builder');
    }

    /**
     * 上传图片
     * @return \think\response\Json
     */
    public function upload()
    {
        $res = Upload::image('file', 'store/product/' . date('Ymd'));
        SystemAttachment::attachmentAdd($res['name'], $res['size'], $res['type'], $res['dir'], $res['thumb_path'], 1, $res['image_type'], $res['time']);
        if (is_array($res))
            return Json::successful('图片上传成功!', ['name' => $res['name'], 'url' => Upload::pathToUrl($res['thumb_path'])]);
        else
            return Json::fail($res);
    }

    /**
     * @Modify: Mr. Lin
     * @function:  添加游玩项目
     * @instructions: 
     * @param {type} 
     * @return: JSON
     */
    public function save_item(Request $request)
    {

        $data = Util::postMore([
            ['mer_id', []],
            ['cate_id', []],
            'store_name',
            'store_info',
            'keyword',
//            'attention',
//            'process',
            ['view',0],
            ['eat',0],
            ['item',0],

            // ['open_date', []],
            ['open_address', []],
            ['unit_name', '件'],
            ['image', []],
            ['slider_image', []],
            ['postage', 0],
            ['ot_price', 0],
            ['price', 0],
            ['sort', 0],
            ['stock', 100],
            'sales',
            ['ficti', 100],
            ['give_integral', 0],
            ['is_show', 0],
            ['cost', 0],
            ['is_hot', 0],
            ['is_benefit', 0],
            ['is_best', 0],
            ['is_new', 0],
            ['mer_use', 0],
            ['type', 2],
        ], $request);
        // if(count($data['cate_id']) < 1) return Json::fail('请选择产品分类');
        $cate_id = $data['cate_id'];
        $data['cate_id'] = implode(',', $data['cate_id']);
        if (!$data['store_name']) return Json::fail('请输入产品名称');

        if (count($data['image']) < 1) return Json::fail('请上传产品图片');
        if (count($data['slider_image']) < 1) return Json::fail('请上传产品轮播图');

//        if (!$data['process']) return Json::fail('请输入项目行程安排');
        if (count($data['open_address']) < 1) return Json::fail('请填写项目地点');
//        if (!$data['attention']) return Json::fail('请输入费用须知注意点');

        if ($data['price'] == '' || $data['price'] < 0) return Json::fail('请输入产品售价');
        if ($data['ot_price'] == '' || $data['ot_price'] < 0) return Json::fail('请输入产品市场价');
        if ($data['stock'] == '' || $data['stock'] < 0) return Json::fail('请输入库存');
        $data['image'] = $data['image'][0];
        $data['slider_image'] = json_encode($data['slider_image']);
        $data['open_address'] = $data['open_address'][0].'/'.$data['open_address'][1];
        $data['add_time'] = time();
        $data['description'] = '';

        $trip_data['view']=$data['view'];
        $trip_data['eat']=$data['eat'];
        $trip_data['item']=$data['item'];
//        $trip_data['process']=$data['process'];

        $trip_id=TripModel::set($trip_data);
        $data['trip_id']=$trip_id['id'];
        $res = ProductModel::set($data);
        foreach ($cate_id as $cid) {
            Db::name('store_product_cate')->insert(['product_id' => $res['id'], 'cate_id' => $cid, 'add_time' => time()]);
        }
        return Json::successful('添加项目成功!');
    }
    /**
     * 保存新建的资源
     *
     * @param  \think\Request  $request
     * @return \think\Response
     */
    public function save(Request $request)
    {
        $data = Util::postMore([
            ['mer_id', []],
            ['cate_id', []],
            'store_name',
            'store_info',
            'keyword',
            ['unit_name', '件'],
            ['image', []],
            ['slider_image', []],
            ['postage', 0],
            ['ot_price', 0],
            ['price', 0],
            ['sort', 0],
            ['stock', 100],
            'sales',
            ['ficti', 100],
            ['give_integral', 0],
            ['is_show', 0],
            ['cost', 0],
            ['is_hot', 0],
            ['is_benefit', 0],
            ['is_best', 0],
            ['is_new', 0],
            ['mer_use', 0],
            ['is_postage', 0],
        ], $request);
        if (count($data['cate_id']) < 1) return Json::fail('请选择产品分类');
        $cate_id = $data['cate_id'];
        $data['cate_id'] = implode(',', $data['cate_id']);
        if (!$data['store_name']) return Json::fail('请输入产品名称');
        if (count($data['image']) < 1) return Json::fail('请上传产品图片');
        if (count($data['slider_image']) < 1) return Json::fail('请上传产品轮播图');
        if ($data['price'] == '' || $data['price'] < 0) return Json::fail('请输入产品售价');
        if ($data['ot_price'] == '' || $data['ot_price'] < 0) return Json::fail('请输入产品市场价');
        if ($data['stock'] == '' || $data['stock'] < 0) return Json::fail('请输入库存');
        $data['image'] = $data['image'][0];
        $data['slider_image'] = json_encode($data['slider_image']);
        $data['add_time'] = time();
        $data['description'] = '';
        $res = ProductModel::set($data);
        foreach ($cate_id as $cid) {
            Db::name('store_product_cate')->insert(['product_id' => $res['id'], 'cate_id' => $cid, 'add_time' => time()]);
        }
        return Json::successful('添加产品成功!');
    }


    public function edit_content($id,$type=1,$field='description')
    {
        if (!$id) return $this->failed('数据不存在');
        $product = ProductModel::get($id);
        if (!$product) return Json::fail('数据不存在!');
        if($type==1){
            $this->assign([
                'content' => ProductModel::where('id', $id)->value($field),
                'field' => $field,
                'action' => Url::build('change_field', ['id' => $id, 'field' => $field])
            ]);
            return $this->fetch('public/edit_content');
        }else{
            $this->assign([
                'content' => $id,
            ]);
            return $this->fetch('public/edit_item_content');
        }
    }

    /**
     * 显示编辑资源表单页.
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function edit($id, $type = '')
    {

        if (!$id) return $this->failed('数据不存在');
        if ($type == 2) {
            $product = ProductModel::get($id);
            $trip=TripModel::get($product->getData('trip_id'));
            if (!$product) return Json::fail('数据不存在!');
            $field = [
                Form::select('mer_id', '所属店铺', (string) $product->getData('mer_id'))->setOptions(function () {
                    $list = MerchantModel::where(['status' => '1'])->select();
                    $menus = [];
                    foreach ($list as $menu) {
                        $menus[] = ['value' => $menu['id'], 'label' => $menu['store_name']]; //,'disabled'=>$menu['pid']== 0];
                    }
                    return $menus;
                })->filterable(1)->multiple(0),
                Form::input('store_name', '产品名称', $product->getData('store_name')),
                Form::input('store_info', '产品简介', $product->getData('store_info'))->type('textarea'),
                Form::input('keyword', '产品关键字', $product->getData('keyword'))->placeholder('多个用英文状态下的逗号隔开'),
                Form::input('unit_name', '产品单位', $product->getData('unit_name')),
                Form::frameImageOne('image', '产品主图片(305*305px)', Url::build('admin/widget.images/index', array('fodder' => 'image')), $product->getData('image'))->icon('image')->width('100%')->height('500px'),
                Form::frameImages('slider_image', '产品轮播图(640*640px)', Url::build('admin/widget.images/index', array('fodder' => 'slider_image')), json_decode($product->getData('slider_image'), 1) ?: [])->maxLength(5)->icon('images')->width('100%')->height('500px'),

//                Form::input('process','行程安排',$trip->getData('process'))->col(24)->placeholder('输入行程和时间格式：事件,时间点（多个事件以|隔开）：例如：早晨海边散步,7:00|坐大巴车前往西湖,8:00'),
                Form::city('open_address', '游玩项目地点')->type('city_area')->trigger('click')->filterable(true)->placeholder($product->getData('open_address')),
//                Form::input('attention', '费用须知',$product->getData('attention'))->placeholder('多个注意点用“ 。”做结尾,例如：1、车费自理。2、早餐自带。'),


                Form::number('view', '景点数',$trip->getData('view'))->min(0)->col(8),
                Form::number('eat', '餐饮次数',$trip->getData('eat'))->min(0)->col(8),
                Form::number('item', '游玩项目数',$trip->getData('item'))->min(0)->col(8),

                Form::number('price', '产品售价', $product->getData('price'))->min(0)->precision(2)->col(8),
                Form::number('ot_price', '产品市场价', $product->getData('ot_price'))->min(0)->col(8),
                Form::number('give_integral', '赠送积分', $product->getData('give_integral'))->min(0)->precision(0)->col(8),
                Form::number('postage', '邮费', $product->getData('postage'))->min(0)->col(8),
                Form::number('sales', '销量', $product->getData('sales'))->min(0)->precision(0)->col(8),
                Form::number('ficti', '虚拟销量', $product->getData('ficti'))->min(0)->precision(0)->col(8),
                Form::number('stock', '库存', ProductModel::getStock($id) > 0 ? ProductModel::getStock($id) : $product->getData('stock'))->min(0)->precision(0)->col(8),
                Form::number('cost', '产品成本价', $product->getData('cost'))->min(0)->col(8),
                Form::number('sort', '排序', $product->getData('sort'))->col(8),

                Form::radio('is_show', '产品状态', $product->getData('is_show'))->options([['label' => '上架', 'value' => 1], ['label' => '下架', 'value' => 0]])->col(8),
                Form::radio('is_hot', '热卖单品', $product->getData('is_hot'))->options([['label' => '是', 'value' => 1], ['label' => '否', 'value' => 0]])->col(8),
                Form::radio('is_benefit', '促销单品', $product->getData('is_benefit'))->options([['label' => '是', 'value' => 1], ['label' => '否', 'value' => 0]])->col(8),
                Form::radio('is_best', '精品推荐', $product->getData('is_best'))->options([['label' => '是', 'value' => 1], ['label' => '否', 'value' => 0]])->col(8),
                Form::radio('is_new', '首发新品', $product->getData('is_new'))->options([['label' => '是', 'value' => 1], ['label' => '否', 'value' => 0]])->col(8),

            ];
            $form = Form::make_post_form('编辑项目', $field, Url::build('update_item', array('id' => $id)), 2);
            $this->assign(compact('form'));
            return $this->fetch('public/form-builder');
        } else {
            $product = ProductModel::get($id);
            if (!$product) return Json::fail('数据不存在!');
            $field = [
                Form::select('mer_id', '所属店铺', (string) $product->getData('mer_id'))->setOptions(function () {
                    $list = MerchantModel::where(['status' => '1'])->select();
                    $menus = [];
                    foreach ($list as $menu) {
                        $menus[] = ['value' => $menu['id'], 'label' => $menu['store_name']]; //,'disabled'=>$menu['pid']== 0];
                    }
                    return $menus;
                })->filterable(1)->multiple(0),
                Form::select('cate_id', '产品分类', explode(',', $product->getData('cate_id')))->setOptions(function () {
                    $list = CategoryModel::getTierList();
                    $menus = [];
                    foreach ($list as $menu) {
                        $menus[] = ['value' => $menu['id'], 'label' => $menu['html'] . $menu['cate_name']]; //,'disabled'=>$menu['pid']== 0];
                    }
                    return $menus;
                })->filterable(1)->multiple(1),
                Form::input('store_name', '产品名称', $product->getData('store_name')),
                Form::input('store_info', '产品简介', $product->getData('store_info'))->type('textarea'),
                Form::input('keyword', '产品关键字', $product->getData('keyword'))->placeholder('多个用英文状态下的逗号隔开'),
                Form::input('unit_name', '产品单位', $product->getData('unit_name')),
                Form::frameImageOne('image', '产品主图片(305*305px)', Url::build('admin/widget.images/index', array('fodder' => 'image')), $product->getData('image'))->icon('image')->width('100%')->height('500px'),
                Form::frameImages('slider_image', '产品轮播图(640*640px)', Url::build('admin/widget.images/index', array('fodder' => 'slider_image')), json_decode($product->getData('slider_image'), 1) ?: [])->maxLength(5)->icon('images')->width('100%')->height('500px'),
                Form::number('price', '产品售价', $product->getData('price'))->min(0)->precision(2)->col(8),
                Form::number('ot_price', '产品市场价', $product->getData('ot_price'))->min(0)->col(8),
                Form::number('give_integral', '赠送积分', $product->getData('give_integral'))->min(0)->precision(0)->col(8),
                Form::number('postage', '邮费', $product->getData('postage'))->min(0)->col(8),
                Form::number('sales', '销量', $product->getData('sales'))->min(0)->precision(0)->col(8)->readonly(1),
                Form::number('ficti', '虚拟销量', $product->getData('ficti'))->min(0)->precision(0)->col(8),
                Form::number('stock', '库存', ProductModel::getStock($id) > 0 ? ProductModel::getStock($id) : $product->getData('stock'))->min(0)->precision(0)->col(8),
                Form::number('cost', '产品成本价', $product->getData('cost'))->min(0)->col(8),
                Form::number('sort', '排序', $product->getData('sort'))->col(8),
                Form::radio('is_show', '产品状态', $product->getData('is_show'))->options([['label' => '上架', 'value' => 1], ['label' => '下架', 'value' => 0]])->col(8),
                Form::radio('is_hot', '热卖单品', $product->getData('is_hot'))->options([['label' => '是', 'value' => 1], ['label' => '否', 'value' => 0]])->col(8),
                Form::radio('is_benefit', '促销单品', $product->getData('is_benefit'))->options([['label' => '是', 'value' => 1], ['label' => '否', 'value' => 0]])->col(8),
                Form::radio('is_best', '精品推荐', $product->getData('is_best'))->options([['label' => '是', 'value' => 1], ['label' => '否', 'value' => 0]])->col(8),
                Form::radio('is_new', '首发新品', $product->getData('is_new'))->options([['label' => '是', 'value' => 1], ['label' => '否', 'value' => 0]])->col(8),
                Form::radio('is_postage', '是否包邮', $product->getData('is_postage'))->options([['label' => '是', 'value' => 1], ['label' => '否', 'value' => 0]])->col(8)
            ];
            $form = Form::make_post_form('编辑产品', $field, Url::build('update', array('id' => $id)), 2);
            $this->assign(compact('form'));
            return $this->fetch('public/form-builder');
        }
    }
   
  /**
     * @Modify: Mr. Lin
     * @function: 游玩项目修改接收参数
     * @instructions: 
     * @param {type} 
     * @return: JSON
     */
    public function update_item(Request $request, $id)
    {
        $data = Util::postMore([
            ['mer_id', ''],
            ['cate_id', []],
            'store_name',
            'store_info',
            'keyword',
//            'attention',
//            'process',
            ['open_address', []],
            ['unit_name', '件'],
            ['image', []],
            ['slider_image', []],
            ['postage', 0],
            ['ot_price', 0],
            ['price', 0],
            ['eat', 0],
            ['view', 0],
            ['item', 0],
            ['sort', 0],
            ['sales', 0],
            ['stock', 0],
            ['ficti', 100],
            ['give_integral', 0],
            ['is_show', 0],
            ['cost', 0],
            ['is_hot', 0],
            ['is_benefit', 0],
            ['is_best', 0],
            ['is_new', 0],
            ['mer_use', 0],
            ['is_postage', 0],
        ], $request);

        if (!$data['store_name']) return Json::fail('请输入产品名称');
//        if (!$data['attention']) return Json::fail('请输入费用须知注意点');
        if (count($data['image']) < 1) return Json::fail('请上传产品图片');
        if (count($data['slider_image']) < 1) return Json::fail('请上传产品轮播图');

//        if (!$data['process']) return Json::fail('请输入项目行程路线');
        if (count($data['open_address']) < 1){
            $data['open_address']=Db::table('eb_store_product')->where('id',"=",$id)->find()['open_address'];
        }else{
            $data['open_address'] = $data['open_address'][0].'/'.$data['open_address'][1];
        }
        // if(count($data['slider_image'])>8) return Json::fail('轮播图最多5张图');
        if ($data['price'] == '' || $data['price'] < 0) return Json::fail('请输入产品售价');
        if ($data['ot_price'] == '' || $data['ot_price'] < 0) return Json::fail('请输入产品市场价');
        if ($data['stock'] == '' || $data['stock'] < 0) return Json::fail('请输入库存');
        // $data['open_date'] = json_encode($data['open_date']);
        $data['image'] = $data['image'][0];
        $data['slider_image'] = json_encode($data['slider_image']);

        $trip_data['view']=$data['view'];
        $trip_data['eat']=$data['eat'];
        $trip_data['item']=$data['item'];
//        $trip_data['process']=$data['process'];
        TripModel::edit($trip_data,Db::table('eb_store_product')->where('id','=',$id)->find()['trip_id']);
        ProductModel::edit($data, $id);
    
      
        return Json::successful('修改成功!');
    }

    /**
     * 保存更新的资源
     *
     * @param  \think\Request  $request
     * @param  int  $id
     * @return \think\Response
     */
    public function update(Request $request, $id)
    {
        $data = Util::postMore([
            ['mer_id', ''],
            ['cate_id', []],
            'store_name',
            'store_info',
            'keyword',
            ['open_date', []],
            ['open_address', []],
            ['unit_name', '件'],
            ['image', []],
            ['slider_image', []],
            ['postage', 0],
            ['ot_price', 0],
            ['price', 0],
            ['sort', 0],
            ['stock', 0],
            ['ficti', 100],
            ['give_integral', 0],
            ['is_show', 0],
            ['cost', 0],
            ['is_hot', 0],
            ['is_benefit', 0],
            ['is_best', 0],
            ['is_new', 0],
            ['mer_use', 0],
            ['is_postage', 0],
        ], $request);
        if (count($data['cate_id']) < 1) return Json::fail('请选择产品分类');
        $cate_id = $data['cate_id'];
        $data['cate_id'] = implode(',', $data['cate_id']);
        if (!$data['store_name']) return Json::fail('请输入产品名称');
        if (count($data['image']) < 1) return Json::fail('请上传产品图片');
        if (count($data['slider_image']) < 1) return Json::fail('请上传产品轮播图');
        // if(count($data['slider_image'])>8) return Json::fail('轮播图最多5张图');
        if ($data['price'] == '' || $data['price'] < 0) return Json::fail('请输入产品售价');
        if ($data['ot_price'] == '' || $data['ot_price'] < 0) return Json::fail('请输入产品市场价');
        if ($data['stock'] == '' || $data['stock'] < 0) return Json::fail('请输入库存');
        $data['image'] = $data['image'][0];
        $data['slider_image'] = json_encode($data['slider_image']);
        ProductModel::edit($data, $id);
        Db::name('store_product_cate')->where('product_id', $id)->delete();
        foreach ($cate_id as $cid) {
            Db::name('store_product_cate')->insert(['product_id' => $id, 'cate_id' => $cid, 'add_time' => time()]);
        }
        return Json::successful('修改成功!');
    }

    public function attr($id)
    {
        if (!$id) return $this->failed('数据不存在!');
        $result = StoreProductAttrResult::getResult($id);
        $image = ProductModel::where('id', $id)->value('image');
        $this->assign(compact('id', 'result', 'image'));
        return $this->fetch();
    }
    /**
     * 生成属性
     * @param int $id
     */
    public function is_format_attr($id = 0)
    {
        if (!$id) return Json::fail('产品不存在');
        list($attr, $detail) = Util::postMore([
            ['items', []],
            ['attrs', []]
        ], $this->request, true);
        $product = ProductModel::get($id);
        if (!$product) return Json::fail('产品不存在');
        $attrFormat = attrFormat($attr)[1];
        if (count($detail)) {
            foreach ($attrFormat as $k => $v) {
                foreach ($detail as $kk => $vv) {
                    if ($v['detail'] == $vv['detail']) {
                        $attrFormat[$k]['price'] = $vv['price'];
                        $attrFormat[$k]['cost'] = isset($vv['cost']) ? $vv['cost'] : $product['cost'];
                        $attrFormat[$k]['sales'] = $vv['sales'];
                        $attrFormat[$k]['pic'] = $vv['pic'];
                        $attrFormat[$k]['check'] = false;
                        break;
                    } else {
                        $attrFormat[$k]['cost'] = $product['cost'];
                        $attrFormat[$k]['price'] = '';
                        $attrFormat[$k]['sales'] = '';
                        $attrFormat[$k]['pic'] = $product['image'];
                        $attrFormat[$k]['check'] = true;
                    }
                }
            }
        } else {
            foreach ($attrFormat as $k => $v) {
                $attrFormat[$k]['cost'] = $product['cost'];
                $attrFormat[$k]['price'] = $product['price'];
                $attrFormat[$k]['sales'] = $product['stock'];
                $attrFormat[$k]['pic'] = $product['image'];
                $attrFormat[$k]['check'] = false;
            }
        }
        return Json::successful($attrFormat);
    }

    public function set_attr($id)
    {
        if (!$id) return $this->failed('产品不存在!');
        list($attr, $detail) = Util::postMore([
            ['items', []],
            ['attrs', []]
        ], $this->request, true);
        $res = StoreProductAttr::createProductAttr($attr, $detail, $id);
        if ($res)
            return $this->successful('编辑属性成功!');
        else
            return $this->failed(StoreProductAttr::getErrorInfo());
    }

    public function clear_attr($id)
    {
        if (!$id) return $this->failed('产品不存在!');
        if (false !== StoreProductAttr::clearProductAttr($id) && false !== StoreProductAttrResult::clearResult($id))
            return $this->successful('清空产品属性成功!');
        else
            return $this->failed(StoreProductAttr::getErrorInfo('清空产品属性失败!'));
    }

    /**
     * 删除指定资源
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function delete($id)
    {

        if (!$id) return $this->failed('数据不存在');
        if (!ProductModel::be(['id' => $id])) return $this->failed('产品数据不存在');
        if (ProductModel::be(['id' => $id, 'is_del' => 1])) {
            $data['is_del'] = 0;
            if (!ProductModel::edit($data, $id))
                return Json::fail(ProductModel::getErrorInfo('恢复失败,请稍候再试!'));
            else
                return Json::successful('成功恢复产品!');
        } else {
            $data['is_del'] = 1;
            if (!ProductModel::edit($data, $id))
                return Json::fail(ProductModel::getErrorInfo('删除失败,请稍候再试!'));
            else
                return Json::successful('成功移到回收站!');
        }
    }




    /**
     * 点赞
     * @param $id
     * @return mixed|\think\response\Json|void
     */
    public function collect($id)
    {
        if (!$id) return $this->failed('数据不存在');
        $product = ProductModel::get($id);
        if (!$product) return Json::fail('数据不存在!');
        $this->assign(StoreProductRelation::getCollect($id));
        return $this->fetch();
    }

    /**
     * 收藏
     * @param $id
     * @return mixed|\think\response\Json|void
     */
    public function like($id)
    {
        if (!$id) return $this->failed('数据不存在');
        $product = ProductModel::get($id);
        if (!$product) return Json::fail('数据不存在!');
        $this->assign(StoreProductRelation::getLike($id));
        return $this->fetch();
    }
    /**
     * 修改产品价格
     * @param Request $request
     */
    public function edit_product_price(Request $request)
    {
        $data = Util::postMore([
            ['id', 0],
            ['price', 0],
        ], $request);
        if (!$data['id']) return Json::fail('参数错误');
        $res = ProductModel::edit(['price' => $data['price']], $data['id']);
        if ($res) return Json::successful('修改成功');
        else return Json::fail('修改失败');
    }

    /**
     * 修改产品库存
     * @param Request $request
     */
    public function edit_product_stock(Request $request)
    {
        $data = Util::postMore([
            ['id', 0],
            ['stock', 0],
        ], $request);
        if (!$data['id']) return Json::fail('参数错误');
        $res = ProductModel::edit(['stock' => $data['stock']], $data['id']);
        if ($res) return Json::successful('修改成功');
        else return Json::fail('修改失败');
    }
}

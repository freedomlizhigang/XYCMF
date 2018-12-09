<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Common\Type;
use Illuminate\Http\Request;
use Validator;
use DB;

class TypeController extends ResponseController
{
    // 取表格数据列表
    public function getList(Request $req)
    {
        try {
            // 所有分类，后端循环出三级来
            $all = Type::select('id','parentid','arrparentid','name','sort')->orderBy('sort','asc')->orderBy('id','asc')->get();
            $tree = $this->toTree($all,'0');
            $list = $this->toTableTree($tree,'0');
            return $this->resData(200,'获取成功...',$list);
        } catch (\Throwable $e) {
            return $this->resData(400,'获取失败，请稍后再试...');
        }
    }
    // 转成树形数组
    private function toTree($data,$pid)
    {
        $tree = [];
        if ($data->count() > 0) {
            foreach($data as $v)
            {
                if ($v->parentid == $pid) {
                    $v = $v->toArray();
                    $v['childs'] = $this->toTree($data,$v['id']);
                    $tree[] = $v;
                }
            }
        }
        return $tree;
    }
    // 转成树形表格用的数据，这个有点坑，必须定义一个循环外的变量来返回，循环内变量会被覆盖导致数据出错
    private $res = [];
    private function toTableTree($data,$pid = 0)
    {
        if (is_null($data) || $data == '') {
            return $res;
        }
        foreach ($data as $v) {
            // 计算level
            $left = 0;
            $level = count(explode(',',$v['arrparentid']));
            $str = '';
            if($level > 1)
            {
                $str .= '|—';
                $left = 10 * $level;
            }
            $this->res[] = ['id'=>$v['id'],'name'=>$str.$v['name'],'sort'=>$v['sort'],'left'=>$left];
            if ($v['childs'] != '')
            {
                $this->toTableTree($v['childs'],$pid);
            }
        }
        return $this->res;
    }
    // 取单条信息
    public function postDetail(Request $req)
    {
        try {
            $validator = Validator::make($req->input(), [
                'type_id' => 'required|integer',
            ]);
             $attrs = array(
                'type_id' => '分类ID',
            );
            $validator->setAttributeNames($attrs);
            if ($validator->fails()) {
                // 如果有错误，提示第一条
                return $this->resData(402,$validator->errors()->all()[0].'...');
            }
            $type_id = $req->input('type_id');
            $detail = Type::select('id','parentid','name','sort')->findOrFail($type_id);
            return $this->resData(200,'获取成功...',$detail);
        } catch (\Throwable $e) {
            return $this->resData(400,'获取失败，请稍后再试...');
        }
    }
    // 创建分类
    public function postCreate(Request $req)
    {
        try {
            $validator = Validator::make($req->input(), [
                'name' => 'required|max:255',
                'parentid' => 'required|integer|min:0',
                'sort' => 'required|integer|min:0',
            ]);
             $attrs = array(
                'name' => '名称',
                'parentid' => '父级分类',
                'sort' => '排序',
            );
            $validator->setAttributeNames($attrs);
            if ($validator->fails()) {
                // 如果有错误，提示第一条
                return $this->resData(402,$validator->errors()->all()[0].'...');
            }
            $name = $req->input('name');
            $parentid = $req->input('parentid');
            $sort = $req->input('sort');
            Type::create(['parentid'=>$parentid,'name'=>$name,'sort'=>$sort]);
            // 更新缓存
            app('com')->updateCache(new Type());
            return $this->resData(200,'创建分类成功...');
        } catch (\Throwable $e) {
            return $this->resData(400,'创建分类失败，请稍后再试...');
        }
    }
    // 修改分类信息
    public function postEdit(Request $req)
    {
        try {
            $validator = Validator::make($req->input(), [
                'type_id' => 'required|integer',
                'name' => 'required|max:255',
                'sort' => 'required|integer|min:0',
            ]);
             $attrs = array(
                'type_id' => '分类ID',
                'name' => '名称',
                'sort' => '排序',
            );
            $validator->setAttributeNames($attrs);
            if ($validator->fails()) {
                // 如果有错误，提示第一条
                return $this->resData(402,$validator->errors()->all()[0].'...');
            }
            Type::where('id',$req->input('type_id'))->update(['name'=>$req->input('name'),'sort'=>$req->input('sort')]);
            return $this->resData(200,'更新分类信息成功...');
        } catch (\Throwable $e) {
            return $this->resData(400,'更新分类信息失败，请稍后再试...');
        }
    }
    // 排序
    public function postSort(Request $req)
    {
        try {
            $validator = Validator::make($req->input(), [
                'type_id' => 'required|integer',
                'sort' => 'required|integer|min:0',
            ]);
             $attrs = array(
                'type_id' => '分类ID',
                'sort' => '排序',
            );
            $validator->setAttributeNames($attrs);
            if ($validator->fails()) {
                // 如果有错误，提示第一条
                return $this->resData(402,$validator->errors()->all()[0].'...');
            }
            Type::where('id',$req->input('type_id'))->update(['sort'=>$req->input('sort')]);
            return $this->resData(200,'更新排序成功...');
        } catch (\Throwable $e) {
            return $this->resData(400,'更新排序失败，请稍后再试...');
        }
    }
    // 删除分类
    public function postRemove(Request $req)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($req->input(), [
                'type_id' => 'required|integer',
            ]);
             $attrs = array(
                'type_id' => '分类ID',
            );
            $validator->setAttributeNames($attrs);
            if ($validator->fails()) {
                // 如果有错误，提示第一条
                return $this->resData(402,$validator->errors()->all()[0].'...');
            }
            $id = $req->input('type_id');
            // 先找出所有子分类
            $allChild = Type::where('id',$id)->value('arrchildid');
            // 所有子分类ID转换为集合，查看是否含有文章或者专题
            $childs = collect(explode(',',$allChild));
            Type::destroy($childs);
            app('com')->updateCache(new Type());
            DB::commit();
            return $this->resData(200,'删除分类成功...');
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->resData(400,'删除分类失败，请稍后再试...');
        }
    }
}

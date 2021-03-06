<?php
/*
 * @Author: 李志刚
 * @CopyRight: 2020-2030 衡水山木枝技术服务有限公司
 * @Date: 2019-01-03 20:14:16
 * @Description: 权限菜单管理
 * @LastEditors: 李志刚
 * @LastEditTime: 2021-02-26 10:32:36
 * @FilePath: /CoinCMF/app/Http/Controllers/Console/Rbac/MenuController.php
 */

namespace App\Http\Controllers\Console\Rbac;

use DB;
use Validator;
use App\Models\Rbac\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\Console\ResponseController;

class MenuController extends ResponseController {
	// 取下拉框菜单
	public function getSelect() {
		try {
			$menus = Menu::select('id', 'parentid', 'name', 'sort', 'url')->orderBy('sort', 'asc')->get();
			$res = [];
			$f_menus = $menus->where('parentid', 0)->all();
			// 只查三级
			foreach ($f_menus as $v) {
				// 一级
				$res[] = ['label' => $v->name, 'value' => $v->id];
				// 二级
				$s_menus = $menus->where('parentid', $v->id)->all();
				foreach ($s_menus as $s) {
					$res[] = ['label' => '|- ' . $s->name, 'value' => $s->id];
					// 三级的
					$t_menus = $menus->where('parentid', $s->id)->all();
					foreach ($t_menus as $t) {
						$res[] = ['label' => '||- ' . $t->name, 'value' => $t->id];
					}
				}
			}
			return $this->resData(200, '获取成功...', $res);
		} catch (\Throwable $e) {
			return $this->resData(500, '获取失败，请稍后再试...');
		}
	}
	// 取左侧权限菜单
	public function getList(Request $req) {
		try {
			// 一级菜单
			$all = Menu::select('id', 'parentid', 'name', 'url', 'icon', 'label')->where('display', '=', '1')->orderBy('sort', 'asc')->orderBy('id', 'asc')->get();
			$leftmenu = array();
			// 判断权限
			$left = $all->where('parentid', 0)->all();
			// 取到用户信息
			$token = $req->header('Authorization');
			$token_info = Redis::get('c-token:' . $token);
			// 解析用户信息，判断权限
			$user = json_decode($token_info);
			// 判断权限
			if (!in_array(1, $user->allRole)) {
				foreach ($left as $k => $v) {
					foreach ($user->allPriv as $url) {
						if ($v['label'] == $url) {
							$leftmenu[$k] = $v;
						}
					}
				}
			} else {
				$leftmenu = $left;
			}
			// 二级菜单
			foreach ($leftmenu as $k => $v) {
				// 取所有下级菜单
				$submenu = $all->where('parentid', '=', $v['id'])->all();
				// 进行权限判断
				if (!in_array(1, $user->allRole)) {
					foreach ($submenu as $s => $v) {
						if (in_array($v['label'], (array) $user->allPriv)) {
							$submenu[$s] = $v;
							$leftmenu[$k]['submenu'] = $submenu;
						}
					}
				} else {
					$leftmenu[$k]['submenu'] = $submenu;
				}
			}
			// 三级菜单
			foreach ($leftmenu as $k => $v) {
				$submenu = $v['submenu'];
				foreach ($submenu as $s => $sub) {
					// 取所有下级菜单
					$there = $all->where('parentid', '=', $sub['id'])->all();
					// 进行权限判断
					if (!in_array(1, $user->allRole)) {
						foreach ($there as $s => $v) {
							if (in_array($v['label'], (array) $user->allPriv)) {
								$there[$s] = $v;
								$submenu[$s]['there'] = $there;
							}
						}
					} else {
						$submenu[$s]['there'] = $there;
					}
				}
				$leftmenu[$k]['submenu'] = $submenu;
			}
			return $this->resData(200, '获取成功...', $leftmenu);
		} catch (\Throwable $e) {
			return $this->resData(500, '获取失败，请稍后再试...');
		}
	}
	// 查出所有有权限的url
	private function allPriv() {
		$rid = session('console')->allRole;
		// 查url
		$priv = Priv::whereIn('role_id', $rid)->pluck('url')->toArray();
		return $priv;
	}
	// 取菜单树
	public function getTree() {
		try {
			// 所有菜单
			$all = Menu::select('id', 'parentid', 'name', 'url', 'display','sort')->orderBy('sort', 'asc')->orderBy('id', 'asc')->get();
			$tree = $this->toTree($all, 0);
			return $this->resData(200, '获取成功...', $tree);
		} catch (\Throwable $e) {
			return $this->resData(500, '获取失败，请稍后再试...');
		}
	}
	// 转成树形菜单数组
	private function toTree($data, $pid) {
		$tree = [];
		if ($data->count() > 0) {
			foreach ($data as $v) {
				if ($v->parentid == $pid) {
					$v = ['menu_id' => $v->id, 'title' => $v->name, 'sort' => $v->sort, 'display' => $v->display , '_showChildren' => true];
					$v['children'] = $this->toTree($data, $v['menu_id']);
					$tree[] = $v;
				}
			}
		}
		return $tree;
	}
	// 添加菜单
	public function postCreate(Request $req) {
		DB::beginTransaction();
		try {
			$validator = Validator::make($req->input(), [
				'parentid' => 'required|integer',
				'name' => 'required|max:255',
				'url' => 'required|max:255',
				'label' => 'required|max:255',
				'display' => 'required|in:true,false',
			]);
			$attrs = array(
				'parentid' => '父级菜单',
				'name' => '菜单名称',
				'url' => '菜单名称',
				'label' => '菜单标签',
				'display' => '状态',
			);
			$validator->setAttributeNames($attrs);
			if ($validator->fails()) {
				// 如果有错误，提示第一条
				return $this->resData(400, $validator->errors()->all()[0] . '...');
			}
			$insert = ['parentid' => $req->input('parentid', 0), 'name' => $req->input('name'), 'url' => $req->input('url'), 'label' => $req->input('label'), 'icon' => $req->input('icon'), 'display' => $req->input('display') == 'true' ? 1 : 0, 'sort' => $req->input('sort')];
			$detail = Menu::create($insert);
			// 更新缓存
			app('com')->updateCache(new Menu());
			DB::commit();
			return $this->resData(200, '添加权限菜单成功...', $detail);
		} catch (\Throwable $e) {
			DB::rollback();
			return $this->resData(500, '添加权限菜单失败，请稍后再试...');
		}
	}
	// 修改菜单
	public function postEdit(Request $req) {
		DB::beginTransaction();
		try {
			$validator = Validator::make($req->input(), [
				'parentid' => 'integer',
				'id' => 'required|integer',
				'name' => 'required|max:255',
				'url' => 'required|max:255',
				'label' => 'required|max:255',
				'display' => 'required|in:true,false',
			]);
			$attrs = array(
				'parentid' => '父菜单',
				'id' => '菜单ID',
				'name' => '菜单名称',
				'url' => '菜单名称',
				'label' => '菜单标签',
				'display' => '状态',
			);
			$validator->setAttributeNames($attrs);
			if ($validator->fails()) {
				// 如果有错误，提示第一条
				return $this->resData(400, $validator->errors()->all()[0] . '...');
			}
			$id = $req->input('id');
			$update = ['parentid' => $req->input('parentid', 0), 'name' => $req->input('name'), 'url' => $req->input('url'), 'label' => $req->input('label'), 'icon' => $req->input('icon'), 'display' => $req->input('display') == 'true' ? 1 : 0, 'sort' => $req->input('sort')];
			Menu::where('id', $id)->update($update);
			// 更新缓存
			app('com')->updateCache(new Menu());
			DB::commit();
			return $this->resData(200, '更新权限菜单成功...');
		} catch (\Throwable $e) {
			DB::rollback();
			return $this->resData(500, '更新权限菜单失败，请稍后再试...');
		}
	}
	// 取单条信息
	public function postDetail(Request $req) {
		try {
			$validator = Validator::make($req->input(), [
				'menu_id' => 'required|integer',
			]);
			$attrs = array(
				'menu_id' => '菜单ID',
			);
			$validator->setAttributeNames($attrs);
			if ($validator->fails()) {
				// 如果有错误，提示第一条
				return $this->resData(400, $validator->errors()->all()[0] . '...');
			}
			$menu_id = $req->input('menu_id');
			$detail = Menu::findOrFail($menu_id);
			return $this->resData(200, '获取成功...', $detail);
		} catch (\Throwable $e) {
			return $this->resData(500, '获取失败，请稍后再试...');
		}
	}
	// 取单条信息
	public function postSort(Request $req)
	{
		try {
			$validator = Validator::make($req->input(), [
				'menu_id' => 'required|integer',
				'sort' => 'required|integer',
			]);
			$attrs = array(
				'menu_id' => '菜单ID',
				'sort' => '排序',
			);
			$validator->setAttributeNames($attrs);
			if ($validator->fails()) {
				// 如果有错误，提示第一条
				return $this->resData(400, $validator->errors()->all()[0] . '...');
			}
			$menu_id = $req->input('menu_id');
			$sort = $req->input('sort');
			Menu::where('id',$menu_id)->update(['sort'=>$sort]);
			return $this->resData(200, '排序成功...');
		} catch (\Throwable $e) {
			return $this->resData(500, '排序失败，请稍后再试...');
		}
	}
	// 删除一条，同时删除子菜单
	public function postRemove(Request $req) {
		DB::beginTransaction();
		try {
			$validator = Validator::make($req->input(), [
				'menu_id' => 'required|integer',
			]);
			$attrs = array(
				'menu_id' => '菜单ID',
			);
			$validator->setAttributeNames($attrs);
			if ($validator->fails()) {
				// 如果有错误，提示第一条
				return $this->resData(400, $validator->errors()->all()[0] . '...');
			}
			$menu_id = $req->input('menu_id');
			$info = Menu::findOrFail($menu_id);
			$arr = explode(',', $info->arrchildid);
			Menu::destroy($arr);
			// 更新缓存
			app('com')->updateCache(new Menu());
			DB::commit();
			return $this->resData(200, '删除成功...');
		} catch (\Throwable $e) {
			DB::rollback();
			return $this->resData(500, '获取失败，请稍后再试...');
		}
	}
}

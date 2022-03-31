<?php
class ModelCatalogExam extends Model {
	public function addExam($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "exam SET parent_id = '" . (int)$data['parent_id'] . "', `top` = '" . (isset($data['top']) ? (int)$data['top'] : 0) . "', `column` = '" . (int)$data['column'] . "', sort_order = '" . (int)$data['sort_order'] . "', status = '" . (int)$data['status'] . "', date_modified = NOW(), date_added = NOW()");

		$exam_id = $this->db->getLastId();

		if (isset($data['image'])) {
			$this->db->query("UPDATE " . DB_PREFIX . "exam SET image = '" . $this->db->escape($data['image']) . "' WHERE exam_id = '" . (int)$exam_id . "'");
		}

		foreach ($data['exam_description'] as $language_id => $value) {
			// Nouveau code pour remplacer meta_description par price*
			$this->db->query("INSERT INTO " . DB_PREFIX . "exam_description SET exam_id = '" . (int)$exam_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($value['name']) . "', description = '" . $this->db->escape($value['description']) . "', meta_title = '" . $this->db->escape($value['meta_title']) . "', price = '" . $this->db->escape($value['price']) . "', meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'");
		}

		// MySQL Hierarchical Data Closure Table Pattern
		$level = 0;

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "exam_path` WHERE exam_id = '" . (int)$data['parent_id'] . "' ORDER BY `level` ASC");

		foreach ($query->rows as $result) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "exam_path` SET `exam_id` = '" . (int)$exam_id . "', `path_id` = '" . (int)$result['path_id'] . "', `level` = '" . (int)$level . "'");

			$level++;
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "exam_path` SET `exam_id` = '" . (int)$exam_id . "', `path_id` = '" . (int)$exam_id . "', `level` = '" . (int)$level . "'");

		if (isset($data['exam_filter'])) {
			foreach ($data['exam_filter'] as $filter_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "exam_filter SET exam_id = '" . (int)$exam_id . "', filter_id = '" . (int)$filter_id . "'");
			}
		}

		if (isset($data['exam_store'])) {
			foreach ($data['exam_store'] as $store_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "exam_to_store SET exam_id = '" . (int)$exam_id . "', store_id = '" . (int)$store_id . "'");
			}
		}
		
		if (isset($data['exam_seo_url'])) {
			foreach ($data['exam_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (!empty($keyword)) {
						$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'exam_id=" . (int)$exam_id . "', keyword = '" . $this->db->escape($keyword) . "'");
					}
				}
			}
		}
		
		// Set which layout to use with this exam
		if (isset($data['exam_layout'])) {
			foreach ($data['exam_layout'] as $store_id => $layout_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "exam_to_layout SET exam_id = '" . (int)$exam_id . "', store_id = '" . (int)$store_id . "', layout_id = '" . (int)$layout_id . "'");
			}
		}

		$this->cache->delete('exam');

		return $exam_id;
	}

	public function editExam($exam_id, $data) {
		$this->db->query("UPDATE " . DB_PREFIX . "exam SET parent_id = '" . (int)$data['parent_id'] . "', `top` = '" . (isset($data['top']) ? (int)$data['top'] : 0) . "', `column` = '" . (int)$data['column'] . "', sort_order = '" . (int)$data['sort_order'] . "', status = '" . (int)$data['status'] . "', date_modified = NOW() WHERE exam_id = '" . (int)$exam_id . "'");

		if (isset($data['image'])) {
			$this->db->query("UPDATE " . DB_PREFIX . "exam SET image = '" . $this->db->escape($data['image']) . "' WHERE exam_id = '" . (int)$exam_id . "'");
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "exam_description WHERE exam_id = '" . (int)$exam_id . "'");

		foreach ($data['exam_description'] as $language_id => $value) {
			// Nouveau code pour remplacer meta_description par price*
			$this->db->query("INSERT INTO " . DB_PREFIX . "exam_description SET exam_id = '" . (int)$exam_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($value['name']) . "', description = '" . $this->db->escape($value['description']) . "', meta_title = '" . $this->db->escape($value['meta_title']) . "', price = '" . $this->db->escape($value['price']) . "', meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'");
		}

		// MySQL Hierarchical Data Closure Table Pattern
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "exam_path` WHERE path_id = '" . (int)$exam_id . "' ORDER BY level ASC");

		if ($query->rows) {
			foreach ($query->rows as $exam_path) {
				// Delete the path below the current one
				$this->db->query("DELETE FROM `" . DB_PREFIX . "exam_path` WHERE exam_id = '" . (int)$exam_path['exam_id'] . "' AND level < '" . (int)$exam_path['level'] . "'");

				$path = array();

				// Get the nodes new parents
				$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "exam_path` WHERE exam_id = '" . (int)$data['parent_id'] . "' ORDER BY level ASC");

				foreach ($query->rows as $result) {
					$path[] = $result['path_id'];
				}

				// Get whats left of the nodes current path
				$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "exam_path` WHERE exam_id = '" . (int)$exam_path['exam_id'] . "' ORDER BY level ASC");

				foreach ($query->rows as $result) {
					$path[] = $result['path_id'];
				}

				// Combine the paths with a new level
				$level = 0;

				foreach ($path as $path_id) {
					$this->db->query("REPLACE INTO `" . DB_PREFIX . "exam_path` SET exam_id = '" . (int)$exam_path['exam_id'] . "', `path_id` = '" . (int)$path_id . "', level = '" . (int)$level . "'");

					$level++;
				}
			}
		} else {
			// Delete the path below the current one
			$this->db->query("DELETE FROM `" . DB_PREFIX . "exam_path` WHERE exam_id = '" . (int)$exam_id . "'");

			// Fix for records with no paths
			$level = 0;

			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "exam_path` WHERE exam_id = '" . (int)$data['parent_id'] . "' ORDER BY level ASC");

			foreach ($query->rows as $result) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "exam_path` SET exam_id = '" . (int)$exam_id . "', `path_id` = '" . (int)$result['path_id'] . "', level = '" . (int)$level . "'");

				$level++;
			}

			$this->db->query("REPLACE INTO `" . DB_PREFIX . "exam_path` SET exam_id = '" . (int)$exam_id . "', `path_id` = '" . (int)$exam_id . "', level = '" . (int)$level . "'");
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "exam_filter WHERE exam_id = '" . (int)$exam_id . "'");

		if (isset($data['exam_filter'])) {
			foreach ($data['exam_filter'] as $filter_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "exam_filter SET exam_id = '" . (int)$exam_id . "', filter_id = '" . (int)$filter_id . "'");
			}
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "exam_to_store WHERE exam_id = '" . (int)$exam_id . "'");

		if (isset($data['exam_store'])) {
			foreach ($data['exam_store'] as $store_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "exam_to_store SET exam_id = '" . (int)$exam_id . "', store_id = '" . (int)$store_id . "'");
			}
		}

		// SEO URL
		$this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE query = 'exam_id=" . (int)$exam_id . "'");

		if (isset($data['exam_seo_url'])) {
			foreach ($data['exam_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (!empty($keyword)) {
						$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'exam_id=" . (int)$exam_id . "', keyword = '" . $this->db->escape($keyword) . "'");
					}
				}
			}
		}
		
		$this->db->query("DELETE FROM " . DB_PREFIX . "exam_to_layout WHERE exam_id = '" . (int)$exam_id . "'");

		if (isset($data['exam_layout'])) {
			foreach ($data['exam_layout'] as $store_id => $layout_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "exam_to_layout SET exam_id = '" . (int)$exam_id . "', store_id = '" . (int)$store_id . "', layout_id = '" . (int)$layout_id . "'");
			}
		}

		$this->cache->delete('exam');
	}

	public function deleteExam($exam_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "exam_path WHERE exam_id = '" . (int)$exam_id . "'");

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "exam_path WHERE path_id = '" . (int)$exam_id . "'");

		foreach ($query->rows as $result) {
			$this->deleteExam($result['exam_id']);
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "exam WHERE exam_id = '" . (int)$exam_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "exam_description WHERE exam_id = '" . (int)$exam_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "exam_filter WHERE exam_id = '" . (int)$exam_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "exam_to_store WHERE exam_id = '" . (int)$exam_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "exam_to_layout WHERE exam_id = '" . (int)$exam_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "assessment_to_exam WHERE exam_id = '" . (int)$exam_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "seo_url WHERE query = 'exam_id=" . (int)$exam_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "coupon_exam WHERE exam_id = '" . (int)$exam_id . "'");

		$this->cache->delete('exam');
	}

	public function repairExams($parent_id = 0) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "exam WHERE parent_id = '" . (int)$parent_id . "'");

		foreach ($query->rows as $exam) {
			// Delete the path below the current one
			$this->db->query("DELETE FROM `" . DB_PREFIX . "exam_path` WHERE exam_id = '" . (int)$exam['exam_id'] . "'");

			// Fix for records with no paths
			$level = 0;

			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "exam_path` WHERE exam_id = '" . (int)$parent_id . "' ORDER BY level ASC");

			foreach ($query->rows as $result) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "exam_path` SET exam_id = '" . (int)$exam['exam_id'] . "', `path_id` = '" . (int)$result['path_id'] . "', level = '" . (int)$level . "'");

				$level++;
			}

			$this->db->query("REPLACE INTO `" . DB_PREFIX . "exam_path` SET exam_id = '" . (int)$exam['exam_id'] . "', `path_id` = '" . (int)$exam['exam_id'] . "', level = '" . (int)$level . "'");

			$this->repairExams($exam['exam_id']);
		}
	}

	public function getExam($exam_id) {
		$query = $this->db->query("SELECT DISTINCT *, (SELECT GROUP_CONCAT(cd1.name ORDER BY level SEPARATOR '&nbsp;&nbsp;&gt;&nbsp;&nbsp;') FROM " . DB_PREFIX . "exam_path cp LEFT JOIN " . DB_PREFIX . "exam_description cd1 ON (cp.path_id = cd1.exam_id AND cp.exam_id != cp.path_id) WHERE cp.exam_id = c.exam_id AND cd1.language_id = '" . (int)$this->config->get('config_language_id') . "' GROUP BY cp.exam_id) AS path FROM " . DB_PREFIX . "exam c LEFT JOIN " . DB_PREFIX . "exam_description cd2 ON (c.exam_id = cd2.exam_id) WHERE c.exam_id = '" . (int)$exam_id . "' AND cd2.language_id = '" . (int)$this->config->get('config_language_id') . "'");
		
		return $query->row;
	}

	public function getExams($data = array()) {
		$sql = "SELECT cp.exam_id AS exam_id, GROUP_CONCAT(cd1.name ORDER BY cp.level SEPARATOR '&nbsp;&nbsp;&gt;&nbsp;&nbsp;') AS name, cp.path_id, c1.parent_id AS parent_id, c1.sort_order, cd2.price AS price FROM " . DB_PREFIX . "exam_path cp LEFT JOIN " . DB_PREFIX . "exam c1 ON (cp.exam_id = c1.exam_id) LEFT JOIN " . DB_PREFIX . "exam c2 ON (cp.path_id = c2.exam_id) LEFT JOIN " . DB_PREFIX . "exam_description cd1 ON (cp.path_id = cd1.exam_id) LEFT JOIN " . DB_PREFIX . "exam_description cd2 ON (cp.exam_id = cd2.exam_id) WHERE cd1.language_id = '" . (int)$this->config->get('config_language_id') . "' AND cd2.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		if (!empty($data['filter_name'])) {
			$sql .= " AND cd2.name LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
		}

		$sql .= " GROUP BY cp.exam_id";

		$sort_data = array(
			'name',
			'sort_order',
			// Nouveau code pour afficher price
			'price'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY sort_order";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getExamDescriptions($exam_id) {
		$exam_description_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "exam_description WHERE exam_id = '" . (int)$exam_id . "'");

		foreach ($query->rows as $result) {
			$exam_description_data[$result['language_id']] = array(
				'name'             => $result['name'],
				'meta_title'       => $result['meta_title'],
				// Nouveau code pour remplacer meta_description par price*
				'price' => $result['price'],
				'meta_keyword'     => $result['meta_keyword'],
				'description'      => $result['description']
			);
		}

		return $exam_description_data;
	}
	
	public function getExamPath($exam_id) {
		$query = $this->db->query("SELECT exam_id, path_id, level FROM " . DB_PREFIX . "exam_path WHERE exam_id = '" . (int)$exam_id . "'");

		return $query->rows;
	}
	
	public function getExamFilters($exam_id) {
		$exam_filter_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "exam_filter WHERE exam_id = '" . (int)$exam_id . "'");

		foreach ($query->rows as $result) {
			$exam_filter_data[] = $result['filter_id'];
		}

		return $exam_filter_data;
	}

	public function getExamStores($exam_id) {
		$exam_store_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "exam_to_store WHERE exam_id = '" . (int)$exam_id . "'");

		foreach ($query->rows as $result) {
			$exam_store_data[] = $result['store_id'];
		}

		return $exam_store_data;
	}
	
	public function getExamSeoUrls($exam_id) {
		$exam_seo_url_data = array();
		
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE query = 'exam_id=" . (int)$exam_id . "'");

		foreach ($query->rows as $result) {
			$exam_seo_url_data[$result['store_id']][$result['language_id']] = $result['keyword'];
		}

		return $exam_seo_url_data;
	}
	
	public function getExamLayouts($exam_id) {
		$exam_layout_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "exam_to_layout WHERE exam_id = '" . (int)$exam_id . "'");

		foreach ($query->rows as $result) {
			$exam_layout_data[$result['store_id']] = $result['layout_id'];
		}

		return $exam_layout_data;
	}

	public function getTotalExams() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "exam");

		return $query->row['total'];
	}
	
	public function getTotalExamsByLayoutId($layout_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "exam_to_layout WHERE layout_id = '" . (int)$layout_id . "'");

		return $query->row['total'];
	}	
}
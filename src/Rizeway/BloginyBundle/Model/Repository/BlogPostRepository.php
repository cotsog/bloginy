<?php

/**
  *  Bloginy, Blog Aggregator
  *  Copyright (C) 2012  Riad Benguella - Rizeway
  *
  *  This program is free software: you can redistribute it and/or modify
  *
  *  it under the terms of the GNU General Public License as published by
  *  the Free Software Foundation, either version 3 of the License, or
  *  any later version.
  *
  *  This program is distributed in the hope that it will be useful,
  *  but WITHOUT ANY WARRANTY; without even the implied warranty of
  *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  * GNU General Public License for more details.
  *
  *  You should have received a copy of the GNU General Public License
  *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Rizeway\BloginyBundle\Model\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

use Rizeway\BloginyBundle\Model\Utils\Operators;
use Rizeway\BloginyBundle\Entity\Blog;

class BlogPostRepository extends EntityRepository
{
    public function customFindOneBy($filters)
    {
      $qb = $this->getBaseQueryBuilder();

      foreach($filters as $filter => $value)
      {
        $where = is_null($qb->getDQLPart('where')) ? 'where' : 'andWhere';
        $qb->$where(\sprintf('blog_post.%s = :%1$s', $filter));
        $qb->setParameter($filter, $value);
      }

      return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Find for a query builder with possible pagination
     *
     * @param integer $page
     * @param integer $max_results
     * @param QueryBuilder $qb
     * @return Rizeway\BloginyBundle\Entity\BlogPost[]
     */
    public function findForQueryBuilder($page = 1, $max_results = null, QueryBuilder $qb = null)
    {
        $qb = is_null($qb) ? $this->getBaseQueryBuilder() : $qb;

        $query = $qb->getQuery();
        if (!is_null($max_results)) {
            $query->setMaxResults($max_results);
            $query->setFirstResult(($page - 1) * $max_results);
        }

        return $query->getResult();
    }
    
    /**
     * Search posts
     * @param string $filter
     * @param int $page
     * @param int $max_results
     * @return Rizeway\BloginyBundle\Entity\BlogPost[]
     */
    public function search($filter, $page = 1, $max_results = null)
    {
        $qb = $this->getSearchFilterQueryBuilder($filter);
        $qb->orderBy('blog_post.created_at', 'DESC');
           
        return $this->findForQueryBuilder($page, $max_results, $qb);
    }
    
    /**
     * Get the search query builder
     * @param string $filter
     * @param QueryBuilder $qb
     * @return Query 
     */
    public function getSearchFilterQueryBuilder($filter, QueryBuilder $qb = null)
    {
        $qb = is_null($qb) ? $this->getBaseQueryBuilder() : $qb;
        $where = is_null($qb->getDQLPart('where')) ? 'where' : 'andWhere';
        $qb->$where($qb->expr()->orX('blog_post.title LIKE :filter', 'blog_post.content LIKE :filter'))
           ->setParameter('filter', '%'.$filter.'%');
        
        return $qb;
    }

    /**
    * find the posts from published after $date
    *
    * @param DateTime $date
    * @param integer $max_results
    * @return Rizeway\BloginyBundle\Entity\BlogPost[]
    */
    public function findFrom(\DateTime $date, $max_results = null,  QueryBuilder $qb = null)
    {
        $qb = $this->getPublishedAtQueryBuilder($date, Operators::OPERATOR_LESS_THAN, $qb);
        $qb->addOrderBy('blog_post.published_at', 'DESC');

        $query = $qb->getQuery();
        
        if (!is_null($max_results)) {
            $query->setMaxResults($max_results);
        }

        return $query->getResult();
    }

    /**
     * find the posts for a blog and published after $date
     * @param Blog|Blog[] $blog
     * @param DateTime $date
     * @param integer $max_results
     * @return Rizeway\BloginyBundle\Entity\BlogPost[]
     */
    public function findForBlog($blog, $date, $max_results = null)
    {
        $qb = $this->getBlogQueryBuilder($blog);
        $qb->orderBy('blog_post.published_at', 'DESC');

        return $this->findFrom($date, $max_results, $qb);
    }


    /**
     * Get the query builder for the publication filter
     *
     * @param \DateTime $date // publication date
     * @param string $operator // Comparaison operator
     * @param QueryBuilder $qb
     * @return QueryBuilder
     */
    public function getPublishedAtQueryBuilder(\DateTime $date, $operator = Operators::OPERATOR_GREATER_THAN , QueryBuilder $qb = null)
    {
        $qb = is_null($qb) ? $this->getBaseQueryBuilder() : $qb;
        $where = is_null($qb->getDQLPart('where')) ? 'where' : 'andWhere';
        $qb->$where(sprintf('(blog_post.published_at %s :published_at)', $operator));
        $qb->setParameter('published_at', $date->format('Y-m-d H:i:sP'));

        return $qb;
    }

    /**
     * Get the query builder with a blog filter
     *     
     * @param Blog|Blog[] $blog
     * @param QueryBuilder $qb
     * @return QueryBuilder
     */
    public function getBlogQueryBuilder($blog, QueryBuilder $qb = null)
    {
        $qb = is_null($qb) ? $this->getBaseQueryBuilder() : $qb;
        $where = is_null($qb->getDQLPart('where')) ? 'where' : 'andWhere';
        
        if (!$blog instanceof Blog) {
            $ids = array();
            foreach ($blog as $b) {
                $ids[] = $b->getId();
            }
            $qb->$where('blog_post.blog IN (:blog)');
            $qb->setParameter('blog', $ids);
        } else {
            $qb->$where('blog_post.blog = :blog');
            $qb->setParameter('blog', $blog->getId());
        }

        return $qb;
    }

    /**
    * Get the base query builder
    *
    * @return QueryBuilder
    */
    public function getBaseQueryBuilder()
    {
        return $this->_em->createQueryBuilder()
          ->select('blog_post')
          ->from('BloginyBundle:BlogPost', 'blog_post');
    }

}
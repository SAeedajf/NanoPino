<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Api\V1\Search;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationDeniedException;
use App\com_pinoox_cms\Cms\Search\SearchQuery;
use App\com_pinoox_cms\Cms\Search\SearchService;
use Throwable;

final readonly class SearchApiFacade
{
    public function __construct(private SearchService $search) {}

    /** @param list<string> $types */
    public function search(
        int $siteId,
        string $text,
        array $types=[],
        ?string $locale=null,
        int $limit=20,
        int $offset=0,
        ?int $actorId=null,
    ): SearchApiResponse {
        try {
            $result=$this->search->search(
                new SearchQuery($siteId,$text,$types,$locale,$limit,$offset),
                $actorId,
            );

            return new SearchApiResponse(200,[
                'data'=>[
                    'hits'=>array_map(
                        static fn($hit):array=>[
                            'id'=>$hit->id,
                            'type'=>$hit->type,
                            'title'=>$hit->title,
                            'url'=>$hit->url,
                            'score'=>$hit->score,
                            'excerpt'=>$hit->excerpt,
                            'metadata'=>$hit->metadata,
                        ],
                        $result->hits,
                    ),
                    'total'=>$result->total,
                    'driver'=>$result->driver,
                    'degraded'=>$result->degraded,
                    'limit'=>$limit,
                    'offset'=>$offset,
                ],
            ]);
        } catch (AuthorizationDeniedException) {
            return $this->error(403,SearchApiErrorCode::Forbidden,'Search is not permitted for this site.');
        } catch (\InvalidArgumentException|\ValueError) {
            return $this->error(422,SearchApiErrorCode::InvalidRequest,'Search request is invalid.');
        } catch (\RuntimeException) {
            return $this->error(503,SearchApiErrorCode::Unavailable,'Search service is unavailable.');
        } catch (Throwable) {
            return $this->error(500,SearchApiErrorCode::InternalError,'Internal search error.');
        }
    }

    private function error(int $status,SearchApiErrorCode $code,string $message):SearchApiResponse
    {
        return new SearchApiResponse($status,[
            'error'=>['code'=>$code->value,'message'=>$message,'details'=>[]],
        ]);
    }
}

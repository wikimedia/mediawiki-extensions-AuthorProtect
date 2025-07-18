<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\User\ActorMigration;

class AuthorProtect {

	/**
	 * @param User $user
	 * @param array &$aRights
	 *
	 * @return bool
	 */
	public static function assignAuthor( $user, &$aRights ) {
		$title = RequestContext::getMain()->getTitle();

		if ( self::userIsAuthor( $user, $title ) ) {
			$aRights[] = 'author';
			$aRights = array_unique( $aRights );
		}

		return true;
	}

	/**
	 * @param Skin $skin
	 * @param string[] &$links
	 *
	 * @return bool
	 */
	public static function makeContentAction( $skin, &$links ) {
		$title = $skin->getTitle();
		$user = $skin->getUser();
		$request = $skin->getRequest();

		if ( self::userIsAuthor( $user, $title )
			&& $user->isAllowed( 'authorprotect' )
			&& !$user->isAllowed( 'protect' )
		) {
			$action = $request->getText( 'action' );
			$links['actions']['authorprotect'] = [
				'class' => $action == 'authorprotect' ? 'selected' : false,
				'text' => wfMessage( self::authorProtectMessage( $title ) ),
				'href' => $title->getLocalUrl( 'action=authorprotect' ),
			];
		}

		return true;
	}

	/**
	 * @param User $user
	 * @param Title $title
	 *
	 * @return string
	 */
	public static function userIsAuthor( $user, $title ) {
		if ( !$title instanceof Title ) {
			// quick hack to prevent the API from messing up.
			return false;
		}

		if ( $user->getID() === 0 ) {
			// don't allow anons, they shouldn't even get this far but just in case...
			return false;
		}

		$id = $title->getArticleID();
		$actorQuery = ActorMigration::newMigration()->getJoin( 'rev_user' );
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$aid = $dbr->selectField(
			[ 'revision' ] + $actorQuery['tables'],
			$actorQuery['fields']['rev_user'],
			[ 'rev_page' => $id ],
			__METHOD__,
			[ 'ORDER BY' => 'rev_timestamp ASC' ],
			$actorQuery['joins']
		);

		return $user->getID() == $aid;
	}

	/**
	 * @param Title $title
	 *
	 * @return string
	 */
	private static function authorProtectMessage( $title ) {
		$restrictionStore = MediaWikiServices::getInstance()->getRestrictionStore();
		foreach ( $restrictionStore->listApplicableRestrictionTypes( $title ) as $type ) {
			if ( in_array( 'author', $restrictionStore->getRestrictions( $title, $type ) ) ) {
				return 'unprotect';
			}
		}
		return 'protect';
	}
}

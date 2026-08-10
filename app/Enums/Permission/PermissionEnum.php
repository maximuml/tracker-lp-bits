<?php

namespace App\Enums\Permission;

enum PermissionEnum: string {
    case BE_ANONYMOUS = 'beanonymous';
    case TORRENT_SET_HR = 'torrent_hr';
    case TORRENT_SET_PRICE = 'torrent-set-price';
    case TORRENT_SET_STICKY = 'torrentsticky';
    case TORRENT_MANAGE = 'torrentmanage';
    case TORRENT_ON_PROMOTION = 'torrentonpromotion';
    case TORRENT_APPROVAL = 'torrent-approval';
    case TORRENT_APPROVAL_ALLOW_AUTOMATIC = 'torrent-approval-allow-automatic';
    case TORRENT_SET_SPECIAL_TAG = 'torrent-set-special-tag';
    case TORRENT_DELETE = 'torrent-delete';
    case TORRENT_HISTORY = 'torrenthistory';
    case TORRENT_STRUCTURE = 'torrentstructure';
    case MOVE_TORRENT = 'movetorrent';
    case UPLOAD = 'upload';
    case ADD_OFFER = 'addoffer';
    case AGAINST_OFFER = 'againstoffer';
    case OFFER_MANAGE = 'offermanage';
    case ASK_RESEED = 'askreseed';
    case BUY_INVITE = 'buyinvite';
    case SEND_INVITE = 'sendinvite';
    case NEWS_MANAGE = 'newsmanage';
    case FORUM_MANAGE = 'forummanage';
    case POST_MANAGE = 'postmanage';
    case POLL_MANAGE = 'pollmanage';
    case COM_MANAGE = 'commanage';
    case SB_MANAGE = 'sbmanage';
    case VIEW_USER_LIST = 'viewuserlist';
    case MANAGE_USER_BASIC_INFO = "prfmanage";
    case MANAGE_USER_CONFIDENTIAL_INFO = "cruprfmanage";
    case VIEW_USER_CONFIDENTIAL_INFO = "userprofile";
    case CONFIDENTIAL_LOG = 'confilog';
    case VIEW_USER_HISTORY = "viewhistory";
    case VIEW_ANONYMOUS = 'viewanonymous';
    case VIEW_INVITE = 'viewinvite';
    case USER_CHANGE_CLASS = 'user-change-class';
    case USER_DELETE = 'user-delete';
    case CHR_MANAGE = 'chrmanage';
    case TOP_TEN = 'topten';
    case LOG = 'log';
    case STAFF_MEMBER = 'staffmem';
    case TORRENT_VIEW_BANNED = "seebanned";

}

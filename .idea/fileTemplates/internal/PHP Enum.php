<?php
#parse("PHP File Header.php")

#if (${NAMESPACE})
namespace ${NAMESPACE};

#end

defined('_JEXEC') || die;

enum ${NAME}#if (${BACKED_TYPE}) : ${BACKED_TYPE} #end{

}
